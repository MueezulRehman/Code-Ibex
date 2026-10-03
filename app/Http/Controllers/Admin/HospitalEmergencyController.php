<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bed;
use App\Models\Department;
use App\Models\Doctor;
use App\Models\HospitalAdmission;
use App\Models\HospitalBilling;
use App\Models\HospitalEmergencyCase;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class HospitalEmergencyController extends Controller
{
    private function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $user->effectiveRestaurantId(), 403);
        abort_unless(
            $user->isRestaurantManager() || in_array($user->staff_type, ['doctor', 'nurse', 'receptionist'], true),
            403
        );

        return $user;
    }

    private function restaurantId(): int
    {
        return (int) $this->user()->effectiveRestaurantId();
    }

    public function index()
    {
        $user = $this->user();
        $restaurantId = (int) $user->effectiveRestaurantId();

        return view('manager.hospital-emergency.index', [
            'cases' => HospitalEmergencyCase::with(['patient', 'doctor', 'nurse', 'department', 'admission'])
                ->where('restaurant_id', $restaurantId)
                ->when(
                    $user->staff_type === 'doctor' && ! $user->isRestaurantManager(),
                    fn ($query) => $query->where('doctor_id', $user->doctorProfile?->id)
                )
                ->when(
                    $user->staff_type === 'nurse' && ! $user->isRestaurantManager(),
                    fn ($query) => $query->where('nurse_id', $user->id)
                )
                ->orderByRaw("FIELD(priority, 'critical', 'urgent', 'routine')")
                ->latest('arrived_at')
                ->paginate(20),
            'patients' => Patient::where('restaurant_id', $restaurantId)->orderBy('name')->get(),
            'doctors' => Doctor::where('restaurant_id', $restaurantId)->where('status', 'active')->orderBy('name')->get(),
            'nurses' => User::where('restaurant_id', $restaurantId)->where('staff_type', 'nurse')->where('is_active', true)->orderBy('name')->get(),
            'departments' => Department::where('restaurant_id', $restaurantId)->where('is_active', true)->orderBy('name')->get(),
            'beds' => Bed::with('ward')->where('restaurant_id', $restaurantId)->where('status', 'available')->orderBy('code')->get(),
            'canManage' => $user->isRestaurantManager(),
        ]);
    }

    public function store(Request $request)
    {
        $user = $this->user();
        abort_unless($user->isRestaurantManager() || in_array($user->staff_type, ['doctor', 'nurse', 'receptionist'], true), 403);
        $restaurantId = (int) $user->effectiveRestaurantId();

        $data = $request->validate([
            'patient_id' => ['required', 'integer', Rule::exists('patients', 'id')->where(fn ($q) => $q->where('restaurant_id', $restaurantId))],
            'priority' => ['required', Rule::in(['critical', 'urgent', 'routine'])],
            'arrived_at' => ['required', 'date'],
            'chief_complaint' => ['required', 'string', 'max:1000'],
            'triage_notes' => ['nullable', 'string', 'max:5000'],
            'doctor_id' => ['nullable', 'integer', Rule::exists('doctors', 'id')->where(fn ($q) => $q->where('restaurant_id', $restaurantId)->where('status', 'active'))],
            'nurse_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where(fn ($q) => $q->where('restaurant_id', $restaurantId)->where('staff_type', 'nurse')->where('is_active', true))],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->where(fn ($q) => $q->where('restaurant_id', $restaurantId)->where('is_active', true))],
            'emergency_charge_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
        ]);

        $case = HospitalEmergencyCase::create($data + [
            'restaurant_id' => $restaurantId,
            'case_number' => 'ER-' . now()->format('Ymd') . '-' . Str::upper(Str::random(5)),
            'status' => 'waiting',
        ]);

        return redirect()->route('manager.hospital-emergency.index')->with('success', "Emergency case {$case->case_number} registered.");
    }

    public function triage(Request $request, int $emergencyCase)
    {
        $user = $this->user();
        $emergencyCase = HospitalEmergencyCase::where('restaurant_id', $this->restaurantId())->findOrFail($emergencyCase);
        $this->tenantCase($emergencyCase);
        abort_unless($user->isRestaurantManager() || $emergencyCase->doctor_id === $user->doctorProfile?->id || $emergencyCase->nurse_id === $user->id, 403);

        $data = $request->validate([
            'priority' => ['required', Rule::in(['critical', 'urgent', 'routine'])],
            'triage_notes' => ['nullable', 'string', 'max:5000'],
            'doctor_id' => ['nullable', 'integer', Rule::exists('doctors', 'id')->where(fn ($q) => $q->where('restaurant_id', $emergencyCase->restaurant_id)->where('status', 'active'))],
            'nurse_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where(fn ($q) => $q->where('restaurant_id', $emergencyCase->restaurant_id)->where('staff_type', 'nurse')->where('is_active', true))],
        ]);

        $emergencyCase->update($data + ['status' => 'triaged', 'triaged_at' => now()]);

        return back()->with('success', 'Emergency triage updated.');
    }

    public function routeCase(Request $request, int $emergencyCase)
    {
        $user = $this->user();
        abort_unless($user->isRestaurantManager(), 403, 'Only hospital managers can route emergency cases.');
        $emergencyCase = HospitalEmergencyCase::where('restaurant_id', $this->restaurantId())->findOrFail($emergencyCase);
        $this->tenantCase($emergencyCase);
        abort_unless(in_array($emergencyCase->status, ['waiting', 'triaged'], true), 422, 'This emergency case has already been routed.');

        $restaurantId = (int) $user->effectiveRestaurantId();
        $data = $request->validate([
            'destination' => ['required', Rule::in(['ward', 'icu', 'observation'])],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->where(fn ($q) => $q->where('restaurant_id', $restaurantId)->where('is_active', true))],
            'bed_id' => ['nullable', 'integer', Rule::exists('beds', 'id')->where(fn ($q) => $q->where('restaurant_id', $restaurantId)->where('status', 'available'))],
            'admitted_at' => ['required', 'date'],
            'routing_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        if (in_array($data['destination'], ['ward', 'icu'], true) && empty($data['bed_id'])) {
            return back()->withErrors(['bed_id' => 'A ward or ICU destination requires an available bed.'])->withInput();
        }

        DB::transaction(function () use ($data, $emergencyCase, $restaurantId): void {
            $bed = null;
            if (! empty($data['bed_id'])) {
                $bed = Bed::with('ward')->where('restaurant_id', $restaurantId)->whereKey($data['bed_id'])
                    ->where('status', 'available')->lockForUpdate()->firstOrFail();
            }

            $admission = HospitalAdmission::create([
                'restaurant_id' => $restaurantId,
                'admission_number' => 'ADM-' . now()->format('Ymd') . '-' . Str::upper(Str::random(5)),
                'patient_id' => $emergencyCase->patient_id,
                'doctor_id' => $emergencyCase->doctor_id,
                'department_id' => $data['department_id'] ?? $emergencyCase->department_id,
                'status' => 'admitted',
                'admitted_at' => $data['admitted_at'],
                'bed_id' => $bed?->id,
                'bed_daily_rate' => $bed?->ward?->daily_rate,
                'notes' => 'Routed from emergency case ' . $emergencyCase->case_number . ' to ' . $data['destination'] . '.',
            ]);

            if ($bed) {
                $bed->update(['status' => 'occupied']);
            }

            if ((float) $emergencyCase->emergency_charge_amount > 0) {
                HospitalBilling::create([
                    'restaurant_id' => $restaurantId,
                    'patient_id' => $emergencyCase->patient_id,
                    'hospital_admission_id' => $admission->id,
                    'charge_type' => 'emergency',
                    'description' => 'Emergency intake and triage - ' . $emergencyCase->case_number,
                    'quantity' => 1,
                    'unit_amount' => $emergencyCase->emergency_charge_amount,
                    'status' => 'draft',
                    'charged_at' => $emergencyCase->arrived_at,
                    'notes' => 'Created when emergency case was routed into active admission.',
                ]);
            }

            $emergencyCase->update([
                'hospital_admission_id' => $admission->id,
                'destination' => $data['destination'],
                'department_id' => $data['department_id'] ?? $emergencyCase->department_id,
                'status' => 'routed',
                'routed_at' => now(),
                'routing_notes' => $data['routing_notes'] ?? null,
            ]);
        });

        return back()->with('success', 'Emergency case routed into an active admission.');
    }

    private function tenantCase(HospitalEmergencyCase $emergencyCase): void
    {
        abort_unless((int) $emergencyCase->restaurant_id === $this->restaurantId(), 404);
    }
}
