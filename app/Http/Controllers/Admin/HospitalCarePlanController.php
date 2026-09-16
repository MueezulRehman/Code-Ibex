<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Models\HospitalAdmission;
use App\Models\HospitalCarePlan;
use App\Models\HospitalCarePlanEvent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class HospitalCarePlanController extends Controller
{
    private function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $user->effectiveRestaurantId(), 403);
        abort_unless($user->isRestaurantManager() || in_array($user->staff_type, ['doctor', 'nurse'], true), 403);

        return $user;
    }

    use AdmissionAccess;


    public function index()
    {
        $user = $this->user();
        $admissionIds = $this->admissionQuery($user)->pluck('id');

        return view('manager.hospital-care-plans.index', [
            'plans' => HospitalCarePlan::with(['admission.patient', 'patient', 'responsibleUser', 'events.changedBy'])
                ->whereIn('hospital_admission_id', $admissionIds)
                ->latest('opened_at')->paginate(20),
            'admissions' => $this->admissionQuery($user)
                ->whereIn('status', ['admitted', 'transferred'])
                ->orderBy('admitted_at')->get(),
            'canCreate' => $user->isRestaurantManager() || $user->staff_type === 'doctor',
            'canEdit' => $user->isRestaurantManager() || $user->staff_type === 'doctor',
            'canUpdateNursing' => $user->isRestaurantManager() || $user->staff_type === 'nurse',
            'users' => User::where('restaurant_id', $user->effectiveRestaurantId())
                ->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $user = $this->user();
        abort_unless($user->isRestaurantManager() || $user->staff_type === 'doctor', 403, 'Only managers, owners, and authorized doctors can create care plans.');
        $restaurantId = (int) $user->effectiveRestaurantId();
        $data = $request->validate($this->planRules($restaurantId, false));
        $admission = HospitalAdmission::where('restaurant_id', $restaurantId)
            ->findOrFail((int) $data['hospital_admission_id']);
        abort_unless($this->canViewAdmission($user, $admission), 403, 'You are not authorized for this admission.');

        $this->validatePlanTimes($data, $admission);
        $status = $data['status'] ?? 'active';
        abort_unless($status !== 'resolved' || ! empty($data['resolved_at']), 422, 'A resolved care plan requires resolved_at.');
        abort_unless($status === 'resolved' || empty($data['resolved_at']), 422, 'Only a resolved care plan may have resolved_at.');
        if ($status === 'resolved') {
            $this->validatePlanTimes($data + ['resolved_at' => $data['resolved_at']], $admission);
        }

        DB::transaction(function () use ($data, $admission, $restaurantId, $user, $status): void {
            $plan = HospitalCarePlan::create($data + [
                'restaurant_id' => $restaurantId,
                'patient_id' => $admission->patient_id,
                'status' => $status,
            ]);
            $this->recordEvent($plan, $user, 'recorded', null, $this->planValues($plan), 'Initial care plan recorded.');
        });

        return back()->with('success', 'Inpatient care plan created.');
    }

    public function update(Request $request, HospitalCarePlan $plan)
    {
        $user = $this->user();
        abort_unless((int) $plan->restaurant_id === (int) $user->effectiveRestaurantId(), 404);
        $admission = $plan->admission()->firstOrFail();
        abort_unless($this->canViewAdmission($user, $admission), 403, 'You are not authorized for this admission.');

        if ($user->staff_type === 'nurse' && ! $user->isRestaurantManager()) {
            $data = $request->validate([
                'status' => ['sometimes', Rule::in(['active', 'resolved', 'cancelled'])],
                'nursing_progress' => ['nullable', 'string', 'max:10000'],
                'nursing_status' => ['nullable', Rule::in(['not_started', 'in_progress', 'completed'])],
            ]);
            $this->assertStatusTransition($plan, $data['status'] ?? $plan->status, $data['status'] ?? null);
            if (($data['status'] ?? $plan->status) === 'resolved' && $plan->status !== 'resolved') {
                $data['resolved_at'] = now();
            }
            if (array_key_exists('nursing_progress', $data) || array_key_exists('nursing_status', $data)) {
                $data['nursing_progress_at'] = now();
            }
            $this->validatePlanTimes($data + [
                'opened_at' => $plan->opened_at,
                'review_due_at' => $plan->review_due_at,
            ], $admission);
            $previous = $this->planValues($plan);
            DB::transaction(function () use ($plan, $data, $user, $previous): void {
                $plan->update($data);
                $this->recordEvent($plan, $user, ($plan->wasChanged('status') ? 'status_changed' : 'nursing_progress'), $previous, $this->planValues($plan), 'Nursing progress updated.');
            });

            return back()->with('success', 'Nursing progress updated.');
        }

        abort_unless($user->isRestaurantManager() || $user->staff_type === 'doctor', 403);
        $data = $request->validate($this->planRules((int) $user->effectiveRestaurantId(), true));
        if (array_key_exists('hospital_admission_id', $data)) {
            abort_unless((int) $data['hospital_admission_id'] === (int) $plan->hospital_admission_id, 422, 'The admission cannot be changed after a care plan is created.');
            unset($data['hospital_admission_id']);
        }
        $this->assertStatusTransition($plan, $data['status'] ?? $plan->status, $data['status'] ?? null);
        $this->validatePlanTimes($data + [
            'opened_at' => $plan->opened_at,
            'review_due_at' => $plan->review_due_at,
        ], $admission);
        if (($data['status'] ?? $plan->status) === 'resolved') {
            abort_unless(! empty($data['resolved_at']) || $plan->resolved_at, 422, 'A resolved care plan requires resolved_at.');
        }
        if (($data['status'] ?? $plan->status) !== 'resolved') {
            abort_unless(empty($data['resolved_at']) && ! $plan->resolved_at, 422, 'Only a resolved care plan may have resolved_at.');
        }

        $previous = $this->planValues($plan);
        DB::transaction(function () use ($plan, $data, $user, $previous): void {
            $plan->update($data);
            $action = $plan->wasChanged('status') ? 'status_changed' : 'corrected';
            $this->recordEvent($plan, $user, $action, $previous, $this->planValues($plan), $data['correction_notes'] ?? 'Care plan updated.');
        });

        return back()->with('success', 'Care plan updated and audit history recorded.');
    }

    private function planRules(int $restaurantId, bool $update): array
    {
        return [
            'hospital_admission_id' => [$update ? 'sometimes' : 'required', 'integer', Rule::exists('hospital_admissions', 'id')->where(fn ($query) => $query->where('restaurant_id', $restaurantId))],
            'problem_diagnosis' => [$update ? 'sometimes' : 'required', 'string', 'max:255'],
            'goal' => [$update ? 'sometimes' : 'required', 'string', 'max:10000'],
            'interventions' => [$update ? 'sometimes' : 'required', 'string', 'max:10000'],
            'responsible_role' => ['nullable', Rule::in(['doctor', 'nurse', 'pharmacist', 'physiotherapist', 'dietitian', 'other'])],
            'responsible_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where(fn ($query) => $query->where('restaurant_id', $restaurantId)->where('is_active', true))],
            'status' => ['sometimes', Rule::in(['active', 'resolved', 'cancelled'])],
            'opened_at' => [$update ? 'sometimes' : 'required', 'date'],
            'review_due_at' => ['nullable', 'date'],
            'resolved_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'nursing_progress' => ['nullable', 'string', 'max:10000'],
            'nursing_status' => ['nullable', Rule::in(['not_started', 'in_progress', 'completed'])],
            'correction_notes' => [$update ? 'required' : 'nullable', 'string', 'max:2000'],
        ];
    }

    private function validatePlanTimes(array $data, HospitalAdmission $admission): void
    {
        $validator = Validator::make([], []);
        $validator->after(function ($validator) use ($data, $admission): void {
            $opened = ! empty($data['opened_at']) ? Carbon::parse($data['opened_at']) : null;
            $reviewDue = ! empty($data['review_due_at']) ? Carbon::parse($data['review_due_at']) : null;
            $resolved = ! empty($data['resolved_at']) ? Carbon::parse($data['resolved_at']) : null;
            $progressAt = ! empty($data['nursing_progress_at']) ? Carbon::parse($data['nursing_progress_at']) : null;
            foreach (['opened_at' => $opened, 'review_due_at' => $reviewDue, 'resolved_at' => $resolved, 'nursing_progress_at' => $progressAt] as $field => $timestamp) {
                if (! $timestamp) {
                    continue;
                }
                if ($timestamp->lt($admission->admitted_at) || ($admission->discharged_at && $timestamp->gt($admission->discharged_at))) {
                    $validator->errors()->add($field, 'The timestamp must be within the admission window.');
                }
            }
            if ($opened && $reviewDue && $reviewDue->lt($opened)) {
                $validator->errors()->add('review_due_at', 'The review due time must be on or after opened_at.');
            }
            if ($opened && $resolved && $resolved->lt($opened)) {
                $validator->errors()->add('resolved_at', 'The resolved time must be on or after opened_at.');
            }
        });
        $validator->validate();
    }

    private function assertStatusTransition(HospitalCarePlan $plan, string $newStatus, ?string $requestedStatus): void
    {
        if ($requestedStatus === null || $newStatus === $plan->status) {
            return;
        }
        abort_unless($plan->status === 'active', 422, 'Resolved or cancelled care plans cannot be reopened.');
    }

    private function planValues(HospitalCarePlan $plan): array
    {
        return collect($plan->only([
            'hospital_admission_id', 'patient_id', 'problem_diagnosis', 'goal',
            'interventions', 'responsible_role', 'responsible_user_id', 'status',
            'opened_at', 'review_due_at', 'resolved_at', 'notes', 'nursing_progress',
            'nursing_status', 'nursing_progress_at',
        ]))->map(static fn ($value) => $value instanceof \DateTimeInterface ? $value->format('Y-m-d H:i:s') : $value)->all();
    }

    private function recordEvent(HospitalCarePlan $plan, User $actor, string $action, ?array $previous, array $new, string $notes): void
    {
        HospitalCarePlanEvent::create([
            'restaurant_id' => $plan->restaurant_id,
            'hospital_care_plan_id' => $plan->id,
            'action' => $action,
            'previous_values' => $previous,
            'new_values' => $new,
            'changed_by' => $actor->id,
            'changed_at' => now(),
            'notes' => $notes,
        ]);
    }
}
