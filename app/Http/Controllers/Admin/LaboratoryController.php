<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HospitalAdmission;
use App\Models\Doctor;
use App\Models\LabOrder;
use App\Models\LabResult;
use App\Models\LabResultEvent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class LaboratoryController extends Controller
{
    private function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $user->effectiveRestaurantId(), 403);
        abort_unless($user->isRestaurantManager() || in_array($user->staff_type, ['doctor', 'nurse'], true), 403);
        return $user;
    }

    private function canViewAdmission(User $user, HospitalAdmission $admission): bool
    {
        if ($user->isRestaurantManager()) {
            return true;
        }

        if ($user->staff_type === 'doctor') {
            $doctor = Doctor::where('user_id', $user->id)->where('restaurant_id', $user->effectiveRestaurantId())->first();
            return $doctor !== null
                && ((int) $admission->doctor_id === (int) $doctor->id
                    || ($doctor->department_id !== null && (int) $admission->department_id === (int) $doctor->department_id));
        }

        return
            $user->staff_type === 'nurse' && $user->nursingAssignments()
                ->where('hospital_admission_id', $admission->id)
                ->where('status', 'active')->exists();
    }

    public function index()
    {
        $user = $this->user();
        $admissionIds = $this->visibleAdmissionIds($user);

        $orders = LabOrder::with(['patient', 'admission', 'orderedBy', 'result.events.changedBy'])
            ->latest('ordered_at')
            ->when($admissionIds !== null, fn ($query) => $query->whereIn('hospital_admission_id', $admissionIds))
            ->paginate(20);

        return view('manager.laboratory.index', [
            'orders' => $orders,
            'admissions' => HospitalAdmission::with('patient')
                ->whereIn('status', ['admitted', 'transferred'])
                ->when($admissionIds !== null, fn ($query) => $query->whereIn('id', $admissionIds))
                ->orderBy('admitted_at')->get(),
            'canOrder' => $user->isRestaurantManager() || $user->staff_type === 'doctor',
            'canManageResults' => $user->isRestaurantManager(),
        ]);
    }

    public function storeOrder(Request $request)
    {
        $user = $this->user();
        abort_unless($user->isRestaurantManager() || $user->staff_type === 'doctor', 403, 'Only doctors and managers can order laboratory tests.');
        $restaurantId = (int) $user->effectiveRestaurantId();
        $admission = HospitalAdmission::where('restaurant_id', $restaurantId)
            ->whereIn('status', ['admitted', 'transferred'])
            ->findOrFail((int) $request->input('hospital_admission_id'));
        abort_unless($this->canViewAdmission($user, $admission), 403, 'You are not assigned to this admission.');
        $data = $request->validate([
            'hospital_admission_id' => ['required', 'integer', Rule::exists('hospital_admissions', 'id')->where(fn ($q) => $q->where('restaurant_id', $restaurantId)->whereIn('status', ['admitted', 'transferred']))],
            'test_name' => ['required', 'string', 'max:255'],
            'priority' => ['required', Rule::in(['routine', 'urgent', 'stat'])],
            'ordered_at' => ['required', 'date', 'after_or_equal:' . $admission->admitted_at->format('Y-m-d H:i:s')],
            'clinical_notes' => ['nullable', 'string', 'max:5000'],
        ]);
        LabOrder::create($data + [
            'restaurant_id' => $restaurantId,
            'patient_id' => $admission->patient_id,
            'ordered_by' => $user->id,
            'order_number' => 'LAB-' . now()->format('Ymd') . '-' . Str::upper(Str::random(5)),
            'status' => 'ordered',
        ]);

        return back()->with('success', 'Laboratory order created.');
    }

    private function visibleAdmissionIds(User $user)
    {
        if ($user->isRestaurantManager()) {
            return null;
        }
        if ($user->staff_type === 'nurse') {
            return $user->nursingAssignments()->where('status', 'active')->pluck('hospital_admission_id');
        }
        if ($user->staff_type !== 'doctor') {
            return collect();
        }
        $doctor = Doctor::where('user_id', $user->id)->where('restaurant_id', $user->effectiveRestaurantId())->first();
        if (! $doctor) {
            return collect();
        }
        return HospitalAdmission::where('restaurant_id', $user->effectiveRestaurantId())
            ->where(function ($query) use ($doctor): void {
                $query->where('doctor_id', $doctor->id);
                if ($doctor->department_id !== null) {
                    $query->orWhere('department_id', $doctor->department_id);
                }
            })->pluck('id');
    }

    public function storeResult(Request $request, LabOrder $order)
    {
        $user = $this->user();
        abort_unless($user->isRestaurantManager(), 403, 'Only managers and owners can manage laboratory results.');
        abort_unless((int) $order->restaurant_id === (int) $user->effectiveRestaurantId(), 404);
        abort_unless($order->status !== 'cancelled' && ! $order->result()->exists(), 422, 'This laboratory order already has a result.');

        $data = $request->validate($this->resultRules($order));
        $payload = $this->preparePayload($order, $data, $user->id);

        DB::transaction(function () use ($payload, $order, $user, $data): void {
            $result = LabResult::create($payload);
            $this->recordEvent($result, $user, $data['status'] === 'verified' ? 'verified' : 'recorded', null, $this->resultSnapshot($result), $data['status'] === 'verified' ? 'Result verified on entry.' : 'Initial result recorded.');
        });

        $order->update(['status' => $data['status'] === 'verified' ? 'resulted' : 'in_progress']);

        return back()->with('success', 'Laboratory result recorded.');
    }

    public function updateResult(Request $request, LabResult $result)
    {
        $user = $this->user();
        abort_unless($user->isRestaurantManager(), 403, 'Only managers can correct laboratory results.');
        abort_unless((int) $result->restaurant_id === (int) $user->effectiveRestaurantId(), 404);

        $order = $result->order()->firstOrFail();
        $data = $request->validate($this->resultRules($order, $result) + [
            'correction_notes' => ['required', 'string', 'max:2000'],
        ]);

        $previous = $this->resultSnapshot($result);
        $candidate = [
            'result_value' => trim((string) $data['result_value']),
            'status' => $data['status'],
            'resulted_at' => $data['resulted_at'],
            'notes' => $data['notes'] ?? null,
            'verified_by' => $data['status'] === 'verified' ? $user->id : null,
            'verified_at' => $data['status'] === 'verified' ? $result->verified_at?->format('Y-m-d H:i:s') : null,
        ];

        if ($previous === $candidate) {
            return back()->withErrors(['result_value' => 'No changes detected. Provide a different result before saving.'])->withInput();
        }

        $payload = [
            'result_value' => trim((string) $data['result_value']),
            'status' => $data['status'],
            'resulted_at' => $data['resulted_at'],
            'notes' => $data['notes'] ?? null,
        ];

        if ($data['status'] === 'verified') {
            $payload['verified_by'] = $user->id;
            $payload['verified_at'] = $result->verified_at ?? now();
        } else {
            $payload['verified_by'] = null;
            $payload['verified_at'] = null;
        }

        DB::transaction(function () use ($result, $user, $payload, $data, $previous): void {
            $result->update($payload);
            $this->recordEvent($result, $user, $data['status'] === 'verified' ? 'verified' : 'edited', $previous, $this->resultSnapshot($result), $data['correction_notes']);
        });

        $result->order->update(['status' => $data['status'] === 'verified' ? 'resulted' : 'in_progress']);

        return back()->with('success', 'Laboratory result corrected and audit history recorded.');
    }

    private function resultRules(LabOrder $order, ?LabResult $result = null): array
    {
        return [
            'result_value' => ['required', 'string', 'max:10000'],
            'resulted_at' => ['required', 'date', 'after_or_equal:' . $order->ordered_at->format('Y-m-d H:i:s')],
            'notes' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::in(['entered', 'verified'])],
        ];
    }

    private function preparePayload(LabOrder $order, array $data, int $editorId): array
    {
        $payload = [
            'restaurant_id' => $order->restaurant_id,
            'lab_order_id' => $order->id,
            'patient_id' => $order->patient_id,
            'hospital_admission_id' => $order->hospital_admission_id,
            'result_value' => trim((string) $data['result_value']),
            'status' => $data['status'],
            'resulted_at' => $data['resulted_at'],
            'notes' => $data['notes'] ?? null,
            'entered_by' => $editorId,
        ];

        if ($data['status'] === 'verified') {
            $payload['verified_by'] = $editorId;
            $payload['verified_at'] = now();
        }

        return $payload;
    }

    private function resultSnapshot(LabResult $result): array
    {
        return [
            'result_value' => $result->result_value,
            'status' => $result->status,
            'resulted_at' => $result->resulted_at?->format('Y-m-d H:i:s'),
            'notes' => $result->notes,
            'verified_by' => $result->verified_by,
            'verified_at' => $result->verified_at?->format('Y-m-d H:i:s'),
        ];
    }

    private function recordEvent(LabResult $result, User $actor, string $action, ?array $previous, array $new, string $notes): void
    {
        LabResultEvent::create([
            'restaurant_id' => $result->restaurant_id,
            'lab_result_id' => $result->id,
            'action' => $action,
            'previous_values' => $previous,
            'new_values' => $new,
            'changed_by' => $actor->id,
            'changed_at' => now(),
            'notes' => $notes,
        ]);
    }
}
