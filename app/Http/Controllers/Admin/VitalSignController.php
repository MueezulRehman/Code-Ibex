<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HospitalAdmission;
use App\Models\User;
use App\Models\VitalSign;
use App\Models\VitalSignEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class VitalSignController extends Controller
{
    private function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $user->effectiveRestaurantId(), 403);
        abort_unless($user->isRestaurantManager() || in_array($user->staff_type, ['nurse', 'doctor'], true), 403);
        return $user;
    }

    private function canAccessAdmission(User $user, HospitalAdmission $admission): bool
    {
        if ($user->isRestaurantManager() || $user->staff_type === 'doctor') {
            return true;
        }

        return $user->staff_type === 'nurse'
            && $admission->status !== 'discharged'
            && $admission->nursingAssignments()
                ->where('nurse_id', $user->id)
                ->where('status', 'active')
                ->exists();
    }

    public function index()
    {
        $user = $this->user();
        $restaurantId = (int) $user->effectiveRestaurantId();
        $admissionIds = null;
        if ($user->staff_type === 'nurse' && ! $user->isRestaurantManager()) {
            $admissionIds = $user->nursingAssignments()
                ->where('status', 'active')
                ->pluck('hospital_admission_id');
        }

        return view('manager.vitals.index', [
            'vitals' => VitalSign::with(['patient', 'admission', 'recorder', 'events.changedBy'])
                ->where('restaurant_id', $restaurantId)
                ->when($admissionIds !== null, fn ($query) => $query->whereIn('hospital_admission_id', $admissionIds))
                ->latest('recorded_at')->paginate(20),
            'admissions' => HospitalAdmission::with('patient')
                ->where('restaurant_id', $restaurantId)
                ->whereIn('status', ['admitted', 'transferred'])
                ->when($admissionIds !== null, fn ($query) => $query->whereIn('id', $admissionIds))
                ->orderBy('admitted_at')->get(),
            'canRecord' => $user->isRestaurantManager() || $user->staff_type === 'nurse',
            'canEdit' => $user->isRestaurantManager(),
        ]);
    }

    public function store(Request $request)
    {
        $user = $this->user();
        abort_unless($user->isRestaurantManager() || $user->staff_type === 'nurse', 403, 'Only nurses and managers can record vital signs.');
        $restaurantId = (int) $user->effectiveRestaurantId();
        $data = $request->validate([
            'hospital_admission_id' => ['required', 'integer', Rule::exists('hospital_admissions', 'id')->where(fn ($q) => $q->where('restaurant_id', $restaurantId)->whereIn('status', ['admitted', 'transferred']))],
        ] + $this->clinicalRules());
        $admission = HospitalAdmission::where('restaurant_id', $restaurantId)->findOrFail($data['hospital_admission_id']);
        abort_unless($this->canAccessAdmission($user, $admission), 403);

        DB::transaction(function () use ($data, $admission, $restaurantId, $user): void {
            $vital = VitalSign::create($data + [
                'restaurant_id' => $restaurantId,
                'patient_id' => $admission->patient_id,
                'recorded_by' => $user->id,
            ]);
            $this->recordEvent($vital, $user, 'recorded', null, $this->clinicalValues($vital), 'Initial observation recorded.');
        });
        return back()->with('success', 'Vital signs recorded.');
    }

    public function update(Request $request, VitalSign $vital)
    {
        $user = $this->user();
        abort_unless($user->isRestaurantManager(), 403, 'Only managers can correct vital signs.');
        $data = $request->validate($this->clinicalRules() + [
            'correction_notes' => ['required', 'string', 'max:2000'],
        ]);
        $previousValues = $this->clinicalValues($vital);
        $changes = collect($data)->except('correction_notes')->all();

        DB::transaction(function () use ($vital, $user, $changes, $previousValues, $data): void {
            $vital->update($changes);
            $this->recordEvent($vital, $user, 'edited', $previousValues, $this->clinicalValues($vital), $data['correction_notes']);
        });

        return back()->with('success', 'Vital signs corrected and audit history recorded.');
    }

    private function clinicalRules(): array
    {
        return [
            'recorded_at' => ['required', 'date'],
            'temperature' => ['nullable', 'numeric', 'between:25,45'],
            'blood_pressure' => ['nullable', 'string', 'max:20'],
            'pulse' => ['nullable', 'integer', 'between:20,250'],
            'respiratory_rate' => ['nullable', 'integer', 'between:5,80'],
            'oxygen_saturation' => ['nullable', 'numeric', 'between:50,100'],
            'weight' => ['nullable', 'numeric', 'between:0.1,500'],
            'pain_score' => ['nullable', 'integer', 'between:0,10'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    private function clinicalValues(VitalSign $vital): array
    {
        return collect($vital->only([
            'recorded_at', 'temperature', 'blood_pressure', 'pulse',
            'respiratory_rate', 'oxygen_saturation', 'weight', 'pain_score', 'notes',
        ]))->map(static fn ($value) => $value instanceof \DateTimeInterface ? $value->format('Y-m-d H:i:s') : $value)->all();
    }

    private function recordEvent(VitalSign $vital, User $actor, string $action, ?array $previous, array $new, string $notes): void
    {
        VitalSignEvent::create([
            'restaurant_id' => $vital->restaurant_id,
            'vital_sign_id' => $vital->id,
            'action' => $action,
            'previous_values' => $previous,
            'new_values' => $new,
            'changed_by' => $actor->id,
            'changed_at' => now(),
            'notes' => $notes,
        ]);
    }
}
