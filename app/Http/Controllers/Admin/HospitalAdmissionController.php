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
use App\Models\HospitalBilling;
use App\Models\HospitalBedStayPeriod;
use App\Models\HospitalPatientInsurance;
use App\Models\HospitalTransfer;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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

    private function tenantUserId(User $user, int $restaurantId): ?int
    {
        $useTenant = false;
        try {
            if (config('database.connections.tenant') && Schema::connection('tenant')->hasTable('users')) {
                $useTenant = true;
            }
        } catch (\Throwable $e) {
            // ignore and fall back to default connection below
            $useTenant = false;
        }

        if ($useTenant) {
            $query = DB::connection('tenant')->table('users')
                ->where(function ($query) use ($user): void {
                    $query->where('id', $user->id)
                        ->orWhere('email', $user->email);
                });

            return $query->value('id')
                ?? DB::connection('tenant')->table('users')->orderBy('id')->value('id');
        }

        // Fall back to default connection users table for test or single-db environments
        if (! Schema::hasTable('users')) {
            return null;
        }

        $query = DB::table('users')
            ->where(function ($query) use ($user): void {
                $query->where('id', $user->id)
                    ->orWhere('email', $user->email);
            });

        return $query->value('id') ?? DB::table('users')->orderBy('id')->value('id');
    }

    private function tenantAuditActorId(User $user, int $restaurantId): int
    {
        return (int) ($this->tenantUserId($user, $restaurantId) ?? $user->id);
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
            'billings:id,restaurant_id,hospital_admission_id,status,total_amount',
            'bedStayPeriods.ward',
            'bedStayPeriods.billing',
            'insurancePolicy',
        ])->latest('admitted_at')->paginate(20);
        return view('manager.admissions.index', [
            'admissions' => $admissions,
            'departments' => Department::where('is_active', true)->orderBy('name')->get(),
            'beds' => Bed::with(['ward', 'location'])->where('status', 'available')->orderBy('code')->get(),
            'patientInsurances' => HospitalPatientInsurance::with('patient')->orderBy('payer')->get(),
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
            'hospital_patient_insurance_id' => ['nullable', 'integer', Rule::exists('hospital_patient_insurances', 'id')->where(fn ($q) => $q->where('restaurant_id', $restaurantId))],
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
        if ($data['hospital_patient_insurance_id'] ?? null) {
            $policy = HospitalPatientInsurance::where('restaurant_id', $restaurantId)
                ->findOrFail($data['hospital_patient_insurance_id']);
            if ((int) $policy->patient_id !== (int) $data['patient_id']) {
                return back()->withErrors([
                    'hospital_patient_insurance_id' => 'The insurance policy does not belong to this patient.',
                ])->withInput();
            }
        }
        $data['restaurant_id'] = $restaurantId;
        $data['admission_number'] = 'ADM-' . now()->format('Ymd') . '-' . Str::upper(Str::random(5));
        $data['status'] = 'admitted';
        DB::transaction(function () use ($data): void {
            $admission = HospitalAdmission::create($data);
            if ($admission->bed_id) {
                $bed = Bed::with('ward')->whereKey($admission->bed_id)->where('status', 'available')->lockForUpdate()->firstOrFail();
                $admission->update(['bed_daily_rate' => $bed->ward?->daily_rate]);
                $bed->update(['status' => 'occupied']);
                if ((float) ($bed->ward?->daily_rate ?? 0) > 0) {
                    $billing = HospitalBilling::create([
                        'restaurant_id' => $admission->restaurant_id,
                        'patient_id' => $admission->patient_id,
                        'hospital_admission_id' => $admission->id,
                        'charge_type' => 'bed',
                        'description' => 'Bed accommodation - '.($bed->ward?->room_category ?: $bed->ward?->name),
                        'quantity' => 1,
                        'unit_amount' => $bed->ward->daily_rate,
                        'status' => 'draft',
                        'charged_at' => $admission->admitted_at,
                        'notes' => 'Initial daily bed charge; subsequent stay-day charging remains manager-controlled.',
                    ]);
                    HospitalBedStayPeriod::create([
                        'restaurant_id' => $admission->restaurant_id, 'hospital_admission_id' => $admission->id,
                        'bed_id' => $bed->id, 'ward_id' => $bed->ward_id, 'started_at' => $admission->admitted_at,
                        'daily_rate' => $bed->ward->daily_rate, 'hospital_billing_id' => $billing->id,
                    ]);
                }
            }
        });
        return redirect()->route('manager.admissions.index')->with('success', 'Patient admitted.');
    }

    public function assignBed(Request $request, int $admission)
    {
        $user = Auth::user();
        $restaurantId = $this->restaurantId();
        $admission = HospitalAdmission::query()->findOrFail($admission);
        $auditActorId = $this->tenantAuditActorId($user, $restaurantId);
        abort_unless($user instanceof User && $user->isRestaurantManager(), 403);
        abort_unless($admission->restaurant_id === $restaurantId && $admission->status !== 'discharged', 404);
        $data = $request->validate([
            'bed_id' => ['required', 'integer', Rule::exists('beds', 'id')->where(fn ($q) => $q->where('restaurant_id', $restaurantId)->where('status', 'available'))],
        ]);
        if ($admission->bed_id && (int) $data['bed_id'] === (int) $admission->bed_id) {
            return back()->withErrors(['bed_id' => 'Admission is already assigned to this bed.']);
        }

        DB::transaction(function () use ($admission, $data, $restaurantId, $auditActorId): void {
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

            if (! $previousBedId) {
                $admission->update(['bed_daily_rate' => $bed->ward?->daily_rate]);
                if ((float) ($bed->ward?->daily_rate ?? 0) > 0) {
                    $billing = HospitalBilling::create([
                        'restaurant_id' => $admission->restaurant_id,
                        'patient_id' => $admission->patient_id,
                        'hospital_admission_id' => $admission->id,
                        'charge_type' => 'bed',
                        'description' => 'Bed accommodation - '.($bed->ward?->room_category ?: $bed->ward?->name),
                        'quantity' => 1,
                        'unit_amount' => $bed->ward->daily_rate,
                        'status' => 'draft',
                        'charged_at' => now(),
                        'posted_by' => $auditActorId,
                        'notes' => 'Initial daily bed charge; subsequent stay-day charging remains manager-controlled.',
                    ]);
                    HospitalBedStayPeriod::create([
                        'restaurant_id' => $admission->restaurant_id, 'hospital_admission_id' => $admission->id,
                        'bed_id' => $bed->id, 'ward_id' => $bed->ward_id, 'started_at' => now(),
                        'daily_rate' => $bed->ward->daily_rate, 'hospital_billing_id' => $billing->id,
                    ]);
                }
            }

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
                'requested_by' => $auditActorId,
                'approved_by' => $auditActorId,
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
                'requested_by' => $auditActorId,
                'transferred_at' => now(),
                'reason' => 'Bed assignment update',
                'notes' => 'Bed assigned by manager.',
            ]);
        });
        return back()->with('success', 'Bed assigned.');
    }

    public function discharge(Request $request, int $admission)
    {
        $user = Auth::user();
        $restaurantId = $this->restaurantId();
        $admission = HospitalAdmission::query()->findOrFail($admission);
        $auditActorId = $this->tenantAuditActorId($user, $restaurantId);
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

        DB::transaction(function () use ($admission, $data, $restaurantId, $auditActorId): void {
            $postedBillings = $admission->billings()->where('status', 'posted')
                ->with('payments')->lockForUpdate()->get();
            abort_if(
                $postedBillings->contains(fn ($billing): bool => $billing->outstandingAmount() > 0),
                422,
                'This patient cannot be discharged while posted hospital billing charges remain unpaid.'
            );
            $dischargedAt = \Carbon\Carbon::parse($data['discharged_at']);
            $periods = $admission->bedStayPeriods()->lockForUpdate()->get();
            if ($periods->isEmpty() && (float) ($admission->bed_daily_rate ?? 0) > 0) {
                $legacyCharge = $admission->billings()->where('charge_type', 'bed')->where('status', 'draft')
                    ->where('description', 'like', 'Bed accommodation -%')->lockForUpdate()->first();
                if ($legacyCharge) {
                    $days = max(1, (int) ceil(max($admission->admitted_at->diffInMinutes($dischargedAt), 0) / 1440));
                    $legacyCharge->update(['quantity' => $days, 'description' => 'Bed accommodation ('.$days.' day'.($days === 1 ? '' : 's').')']);
                }
            }
            foreach ($periods as $period) {
                $periodEnd = $period->ended_at ?: $dischargedAt;
                $days = max(1, (int) ceil(max($period->started_at->diffInMinutes($periodEnd), 0) / 1440));
                $billing = $period->billing()->lockForUpdate()->first();
                if ($billing && $billing->status === 'draft') {
                    $billing->update(['quantity' => $days, 'description' => 'Bed accommodation ('.$days.' day'.($days === 1 ? '' : 's').')']);
                }
                if (! $period->ended_at) {
                    $period->update(['ended_at' => $dischargedAt]);
                }
            }
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
                'discharged_by' => $auditActorId,
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
                'changed_by' => $auditActorId,
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

        $auditActorId = $this->tenantAuditActorId($user, $restaurantId);

        DB::transaction(function () use ($summary, $restaurantId, $auditActorId, $previous, $new, $data): void {
            $locked = DischargeSummary::where('restaurant_id', $restaurantId)->lockForUpdate()->findOrFail($summary->id);
            $locked->update($new);
            DischargeSummaryEvent::create([
                'restaurant_id' => $restaurantId,
                'discharge_summary_id' => $locked->id,
                'action' => 'edited',
                'previous_snapshot' => $previous,
                'new_snapshot' => $new,
                'changed_by' => $auditActorId,
                'changed_at' => now(),
                'correction_reason' => $data['correction_notes'],
            ]);
        });

        return back()->with('success', 'Discharge summary corrected.');
    }

    public function transfer(Request $request, int $admission)
    {
        $user = Auth::user();
        $restaurantId = $this->restaurantId();
        $admission = HospitalAdmission::query()->findOrFail($admission);
        $auditActorId = $this->tenantAuditActorId($user, $restaurantId);
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

        DB::transaction(function () use ($data, $admission, $restaurantId, $auditActorId): void {
            $newBed = null;
            if (! empty($data['bed_id'])) {
                $newBed = Bed::with('ward')->where('restaurant_id', $restaurantId)->whereKey($data['bed_id'])
                    ->where('status', 'available')->lockForUpdate()->firstOrFail();
            }

            $oldBedId = $admission->bed_id;
            $oldDepartmentId = $admission->department_id;
            $previousWardId = $oldBedId ? Bed::whereKey($oldBedId)->value('ward_id') : null;
            $newWardId = $newBed?->ward_id;
            $newDepartmentId = $data['department_id'] ?? $oldDepartmentId;
            $transferredAt = \Carbon\Carbon::parse($data['transferred_at']);

            if ($oldBedId && (! $newBed || $newBed->id !== $oldBedId)) {
                Bed::whereKey($oldBedId)->update(['status' => 'available']);
            }
            if ($newBed) {
                $newBed->update(['status' => 'occupied']);
            }
            $activePeriod = HospitalBedStayPeriod::where('hospital_admission_id', $admission->id)
                ->whereNull('ended_at')->lockForUpdate()->first();
            if (! $activePeriod && (float) ($admission->bed_daily_rate ?? 0) > 0) {
                $initialBilling = $admission->billings()->where('charge_type', 'bed')->where('status', 'draft')
                    ->where('description', 'like', 'Bed accommodation -%')->oldest()->first();
                $activePeriod = HospitalBedStayPeriod::create([
                    'restaurant_id' => $restaurantId, 'hospital_admission_id' => $admission->id,
                    'bed_id' => $oldBedId, 'ward_id' => $previousWardId, 'started_at' => $admission->admitted_at,
                    'daily_rate' => $admission->bed_daily_rate, 'hospital_billing_id' => $initialBilling?->id,
                ]);
            }
            if ($activePeriod) {
                $activePeriod->update(['ended_at' => $transferredAt]);
            }
            if ($newBed && (float) ($newBed->ward?->daily_rate ?? 0) > 0) {
                $billing = HospitalBilling::create([
                    'restaurant_id' => $restaurantId, 'patient_id' => $admission->patient_id,
                    'hospital_admission_id' => $admission->id, 'charge_type' => 'bed',
                    'description' => 'Bed accommodation - '.($newBed->ward?->room_category ?: $newBed->ward?->name),
                    'quantity' => 1, 'unit_amount' => $newBed->ward->daily_rate, 'status' => 'draft',
                    'charged_at' => $transferredAt, 'posted_by' => $auditActorId,
                    'notes' => 'New bed-stay pricing period.',
                ]);
                HospitalBedStayPeriod::create([
                    'restaurant_id' => $restaurantId, 'hospital_admission_id' => $admission->id,
                    'bed_id' => $newBed->id, 'ward_id' => $newBed->ward_id, 'started_at' => $transferredAt,
                    'daily_rate' => $newBed->ward->daily_rate, 'hospital_billing_id' => $billing->id,
                ]);
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
                    'requested_by' => $auditActorId,
                    'approved_by' => $auditActorId,
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
                'requested_by' => $auditActorId,
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
