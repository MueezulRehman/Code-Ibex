<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Bed;
use App\Models\BedTransfer;
use App\Models\DischargeSummary;
use App\Models\DischargeSummaryEvent;
use App\Models\Doctor;
use App\Models\HospitalAdmission;
use App\Models\HospitalTransfer;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class HospitalAdmissionController extends Controller
{
    private function restaurantId(): int
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);
        $id = $user->effectiveRestaurantId();
        abort_unless($id !== null, 403);
        return (int) $id;
    }

    public function index()
    {
        $admissions = HospitalAdmission::with([
            'patient.hospitalAdmissions.doctor',
            'patient.hospitalAdmissions.department',
            'patient.hospitalAdmissions.dischargeSummary',
            'doctor',
            'department',
            'bed.location',
            'bedTransfers.previousBed',
            'bedTransfers.newBed',
            'dischargeSummary',
            'billings:id,hospital_admission_id,status,total_amount',
        ])->latest('admitted_at')->paginate(20);
        return view('manager.admissions.index', [
            'admissions' => $admissions,
            'departments' => Department::where('is_active', true)->orderBy('name')->get(),
            'beds' => Bed::with(['ward', 'location'])->where('status', 'available')->orderBy('code')->get(),
        ]);
    }

    public function create()
    {
        return view('manager.admissions.create', [
            'patients' => Patient::orderBy('name')->get(),
            'doctors' => Doctor::where('status', 'active')->orderBy('name')->get(),
            'departments' => Department::where('is_active', true)->orderBy('name')->get(),
            'beds' => Bed::with(['ward', 'location'])->where('status', 'available')->orderBy('code')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $restaurantId = $this->restaurantId();
        $data = $request->validate([
            'patient_id' => ['required', 'integer', Rule::exists('patients', 'id')->where(fn ($q) => $q->where('restaurant_id', $restaurantId))],
            'doctor_id' => ['nullable', 'integer', Rule::exists('doctors', 'id')->where(fn ($q) => $q->where('restaurant_id', $restaurantId))],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->where(fn ($q) => $q->where('restaurant_id', $restaurantId)->where('is_active', true))],
            'bed_id' => ['nullable', 'integer', Rule::exists('beds', 'id')->where(fn ($q) => $q->where('restaurant_id', $restaurantId)->where('status', 'available'))],
            'admitted_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $hasActiveAdmission = HospitalAdmission::where('restaurant_id', $restaurantId)
            ->where('patient_id', $data['patient_id'])
            ->whereIn('status', ['admitted', 'transferred'])
            ->exists();
        if ($hasActiveAdmission) {
            return back()->withErrors([
                'patient_id' => 'This patient already has an active hospital admission.',
            ])->withInput();
        }
        $data['restaurant_id'] = $restaurantId;
        $data['admission_number'] = 'ADM-' . now()->format('Ymd') . '-' . Str::upper(Str::random(5));
        $data['status'] = 'admitted';
        DB::transaction(function () use ($data): void {
            $admission = HospitalAdmission::create($data);
            if ($admission->bed_id) {
                Bed::whereKey($admission->bed_id)->where('status', 'available')->lockForUpdate()->firstOrFail()
                    ->update(['status' => 'occupied']);
            }
        });
        return redirect()->route('manager.admissions.index')->with('success', 'Patient admitted.');
    }

    public function assignBed(Request $request, HospitalAdmission $admission)
    {
        $user = Auth::user();
        $restaurantId = $this->restaurantId();
        abort_unless($user instanceof User && $user->isRestaurantManager(), 403);
        abort_unless($admission->restaurant_id === $restaurantId && $admission->status !== 'discharged', 404);
        $data = $request->validate([
            'bed_id' => ['required', 'integer', Rule::exists('beds', 'id')->where(fn ($q) => $q->where('restaurant_id', $restaurantId)->where('status', 'available'))],
        ]);
        if ($admission->bed_id && (int) $data['bed_id'] === (int) $admission->bed_id) {
            return back()->withErrors(['bed_id' => 'Admission is already assigned to this bed.']);
        }

        DB::transaction(function () use ($admission, $data, $restaurantId, $user): void {
            $bed = Bed::where('restaurant_id', $restaurantId)->whereKey($data['bed_id'])
                ->where('status', 'available')->lockForUpdate()->firstOrFail();
            $previousBedId = $admission->bed_id;
            $previousDepartmentId = $admission->department_id;
            $previousWardId = $previousBedId ? Bed::whereKey($previousBedId)->value('ward_id') : null;
            $newWardId = $bed->ward_id;
            $newDepartmentId = $previousDepartmentId;

            if ($previousBedId && $previousBedId !== $bed->id) {
                Bed::whereKey($previousBedId)->update(['status' => 'available']);
            }

            $admission->update(['bed_id' => $bed->id, 'department_id' => $newDepartmentId]);
            $bed->update(['status' => 'occupied']);

            BedTransfer::create([
                'restaurant_id' => $restaurantId,
                'hospital_admission_id' => $admission->id,
                'patient_id' => $admission->patient_id,
                'previous_bed_id' => $previousBedId,
                'new_bed_id' => $bed->id,
                'previous_ward_id' => $previousWardId,
                'new_ward_id' => $newWardId,
                'previous_department_id' => $previousDepartmentId,
                'new_department_id' => $newDepartmentId,
                'requested_by' => $user->id,
                'approved_by' => $user->id,
                'transferred_at' => now(),
                'reason' => 'Bed assignment update',
                'notes' => 'Bed assigned by manager.',
            ]);

            HospitalTransfer::create([
                'restaurant_id' => $restaurantId,
                'hospital_admission_id' => $admission->id,
                'from_department_id' => $previousDepartmentId,
                'to_department_id' => $newDepartmentId,
                'from_bed_id' => $previousBedId,
                'to_bed_id' => $bed->id,
                'requested_by' => $user->id,
                'transferred_at' => now(),
                'reason' => 'Bed assignment update',
                'notes' => 'Bed assigned by manager.',
            ]);
        });
        return back()->with('success', 'Bed assigned.');
    }

    public function discharge(Request $request, HospitalAdmission $admission)
    {
        $user = Auth::user();
        $restaurantId = $this->restaurantId();
        abort_unless($user instanceof User && $user->isRestaurantManager(), 403);
        abort_unless($admission->restaurant_id === $restaurantId && $admission->status !== 'discharged', 404);
        $data = $request->validate([
            'discharge_diagnosis' => ['required', 'string', 'max:10000'],
            'discharge_medications' => ['nullable', 'string', 'max:10000'],
            'follow_up_instructions' => ['nullable', 'string', 'max:10000'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'discharged_at' => ['required', 'date', 'after_or_equal:' . $admission->admitted_at->format('Y-m-d H:i:s')],
        ]);

        if ($admission->hasUnpaidPostedBilling()) {
            return back()->withErrors([
                'billing' => 'This patient cannot be discharged while posted hospital billing charges remain unpaid. Settle or void the outstanding charges first.',
            ])->withInput();
        }

        DB::transaction(function () use ($admission, $data, $restaurantId, $user): void {
            abort_if(
                $admission->billings()->where('status', 'posted')->lockForUpdate()->exists(),
                422,
                'This patient cannot be discharged while posted hospital billing charges remain unpaid.'
            );
            if ($admission->bed_id) {
                Bed::where('restaurant_id', $restaurantId)->whereKey($admission->bed_id)
                    ->lockForUpdate()->firstOrFail()->update(['status' => 'available']);
            }
            DischargeSummary::create([
                'restaurant_id' => $restaurantId,
                'hospital_admission_id' => $admission->id,
                'patient_id' => $admission->patient_id,
                'discharge_diagnosis' => $data['discharge_diagnosis'],
                'discharge_medications' => $data['discharge_medications'] ?? null,
                'follow_up_instructions' => $data['follow_up_instructions'] ?? null,
                'notes' => $data['notes'] ?? null,
                'discharged_by' => $user->id,
                'discharged_at' => $data['discharged_at'],
            ]);
            DischargeSummaryEvent::create([
                'restaurant_id' => $restaurantId,
                'discharge_summary_id' => DischargeSummary::where('hospital_admission_id', $admission->id)->value('id'),
                'action' => 'recorded',
                'previous_snapshot' => null,
                'new_snapshot' => [
                    'discharge_diagnosis' => $data['discharge_diagnosis'],
                    'discharge_medications' => $data['discharge_medications'] ?? null,
                    'follow_up_instructions' => $data['follow_up_instructions'] ?? null,
                    'notes' => $data['notes'] ?? null,
                ],
                'changed_by' => $user->id,
                'changed_at' => now(),
                'correction_reason' => null,
            ]);
            $admission->update([
                'status' => 'discharged',
                'bed_id' => null,
                'discharged_at' => $data['discharged_at'],
            ]);
        });

        return back()->with('success', 'Discharge summary recorded.');
    }

    public function updateDischargeSummary(Request $request, DischargeSummary $summary)
    {
        $user = Auth::user();
        $restaurantId = $this->restaurantId();
        abort_unless($user instanceof User && $user->isRestaurantManager(), 403);
        abort_unless($summary->restaurant_id === $restaurantId, 404);

        $data = $request->validate([
            'discharge_diagnosis' => ['required', 'string', 'max:10000'],
            'discharge_medications' => ['nullable', 'string', 'max:10000'],
            'follow_up_instructions' => ['nullable', 'string', 'max:10000'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'correction_notes' => ['required', 'string', 'max:2000'],
        ]);
        $fields = ['discharge_diagnosis', 'discharge_medications', 'follow_up_instructions', 'notes'];
        $previous = collect($fields)->mapWithKeys(fn ($field) => [$field => $summary->{$field}])->all();
        $new = collect($fields)->mapWithKeys(fn ($field) => [$field => $data[$field] ?? null])->all();
        if ($previous === $new) {
            return back()->withErrors(['correction_notes' => 'The correction does not change the discharge summary.'])->withInput();
        }

        DB::transaction(function () use ($summary, $restaurantId, $user, $previous, $new, $data): void {
            $locked = DischargeSummary::where('restaurant_id', $restaurantId)->lockForUpdate()->findOrFail($summary->id);
            $locked->update($new);
            DischargeSummaryEvent::create([
                'restaurant_id' => $restaurantId,
                'discharge_summary_id' => $locked->id,
                'action' => 'edited',
                'previous_snapshot' => $previous,
                'new_snapshot' => $new,
                'changed_by' => $user->id,
                'changed_at' => now(),
                'correction_reason' => $data['correction_notes'],
            ]);
        });

        return back()->with('success', 'Discharge summary corrected.');
    }

    public function transfer(Request $request, HospitalAdmission $admission)
    {
        $user = Auth::user();
        $restaurantId = $this->restaurantId();
        abort_unless($user instanceof User && $user->isRestaurantManager(), 403);
        abort_unless($admission->restaurant_id === $restaurantId && $admission->status !== 'discharged', 404);
        $data = $request->validate([
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->where(fn ($q) => $q->where('restaurant_id', $restaurantId)->where('is_active', true))],
            'bed_id' => ['nullable', 'integer', Rule::exists('beds', 'id')->where(fn ($q) => $q->where('restaurant_id', $restaurantId)->where('status', 'available'))],
            'transferred_at' => ['required', 'date', 'after_or_equal:' . $admission->admitted_at->format('Y-m-d H:i:s'), 'before_or_equal:now'],
            'reason' => ['required', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $intendedDepartmentId = $data['department_id'] ?? $admission->department_id;
        if ($admission->bed_id && ! empty($data['bed_id']) && (int) $data['bed_id'] === (int) $admission->bed_id && (int) $intendedDepartmentId === (int) $admission->department_id) {
            return back()->withErrors(['bed_id' => 'Admission is already assigned to this bed.']);
        }

        if (! $admission->bed_id && empty($data['bed_id']) && (int) ($data['department_id'] ?? $admission->department_id) === (int) $admission->department_id) {
            return back()->withErrors(['bed_id' => 'Select a bed or release the current bed before recording an empty transfer.']);
        }

        DB::transaction(function () use ($data, $admission, $restaurantId, $user): void {
            $newBed = null;
            if (! empty($data['bed_id'])) {
                $newBed = Bed::where('restaurant_id', $restaurantId)->whereKey($data['bed_id'])
                    ->where('status', 'available')->lockForUpdate()->firstOrFail();
            }

            $oldBedId = $admission->bed_id;
            $oldDepartmentId = $admission->department_id;
            $previousWardId = $oldBedId ? Bed::whereKey($oldBedId)->value('ward_id') : null;
            $newWardId = $newBed?->ward_id;
            $newDepartmentId = $data['department_id'] ?? $oldDepartmentId;

            if ($oldBedId && (! $newBed || $newBed->id !== $oldBedId)) {
                Bed::whereKey($oldBedId)->update(['status' => 'available']);
            }
            if ($newBed) {
                $newBed->update(['status' => 'occupied']);
            }

            $bedTransferCreated = false;
            if ($oldBedId !== $newBed?->id || $previousWardId !== $newWardId || $oldDepartmentId !== $newDepartmentId) {
                $bedTransferCreated = true;
                BedTransfer::create([
                    'restaurant_id' => $restaurantId,
                    'hospital_admission_id' => $admission->id,
                    'patient_id' => $admission->patient_id,
                    'previous_bed_id' => $oldBedId,
                    'new_bed_id' => $newBed?->id,
                    'previous_ward_id' => $previousWardId,
                    'new_ward_id' => $newWardId,
                    'previous_department_id' => $oldDepartmentId,
                    'new_department_id' => $newDepartmentId,
                    'requested_by' => $user->id,
                    'approved_by' => $user->id,
                    'transferred_at' => $data['transferred_at'],
                    'reason' => $data['reason'],
                    'notes' => $data['notes'] ?? null,
                ]);
            }

            HospitalTransfer::create([
                'restaurant_id' => $restaurantId,
                'hospital_admission_id' => $admission->id,
                'from_department_id' => $oldDepartmentId,
                'to_department_id' => $newDepartmentId,
                'from_bed_id' => $oldBedId,
                'to_bed_id' => $newBed?->id,
                'requested_by' => $user->id,
                'transferred_at' => $data['transferred_at'],
                'reason' => $data['reason'],
                'notes' => $data['notes'] ?? null,
            ]);
            $admission->update([
                'department_id' => $newDepartmentId,
                'bed_id' => $newBed?->id,
                'status' => 'transferred',
            ]);

            if ($bedTransferCreated && $admission->wasChanged('bed_id')) {
                $admission->refresh();
            }
        });

        return back()->with('success', 'Admission transfer recorded.');
    }
}
