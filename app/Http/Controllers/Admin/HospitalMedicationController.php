<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Models\HospitalAdmission;
use App\Models\HospitalMedicationAdministration;
use App\Models\HospitalMedicationFulfillment;
use App\Models\HospitalMedicationOrder;
use App\Models\Medicine;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use App\Services\HospitalMedicationFulfillmentService;

class HospitalMedicationController extends Controller
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

        if ($user->staff_type === 'nurse') {
            return $user->nursingAssignments()
                ->where('hospital_admission_id', $admission->id)
                ->where('status', 'active')->exists();
        }

        $doctor = Doctor::where('user_id', $user->id)
            ->where('restaurant_id', $user->effectiveRestaurantId())->first();

        return $doctor !== null
            && ((int) $admission->doctor_id === (int) $doctor->id
                || ($doctor->department_id !== null && (int) $admission->department_id === (int) $doctor->department_id));
    }

    private function canRecord(User $user, HospitalAdmission $admission): bool
    {
        return $user->isRestaurantManager()
            || ($user->staff_type === 'nurse' && $user->nursingAssignments()
                ->where('hospital_admission_id', $admission->id)
                ->where('status', 'active')->exists());
    }

    private function assertAdmissionWindow(HospitalAdmission $admission, \Carbon\CarbonInterface $timestamp, string $field): void
    {
        abort_unless($timestamp->greaterThanOrEqualTo($admission->admitted_at), 422, "{$field} cannot be before the admission.");
        if ($admission->discharged_at) {
            abort_unless($timestamp->lessThanOrEqualTo($admission->discharged_at), 422, "{$field} cannot be after discharge.");
        }
    }

    public function index()
    {
        $user = $this->user();
        $admissionIds = $user->isRestaurantManager()
            ? null
            : HospitalAdmission::where('restaurant_id', $user->effectiveRestaurantId())
                ->get()->filter(fn (HospitalAdmission $admission) => $this->canViewAdmission($user, $admission))->pluck('id');

        return view('manager.hospital-medication.index', [
            'orders' => HospitalMedicationOrder::with(['patient', 'admission', 'medicine', 'orderedBy', 'administrations.recordedBy', 'fulfillment.batch', 'fulfillment.batches.batch'])
                ->when($admissionIds !== null, fn ($query) => $query->whereIn('hospital_admission_id', $admissionIds))
                ->latest('ordered_at')->paginate(20),
            'admissions' => HospitalAdmission::with('patient')
                ->whereIn('status', ['admitted', 'transferred'])
                ->when($admissionIds !== null, fn ($query) => $query->whereIn('id', $admissionIds))
                ->orderBy('admitted_at')->get(),
            'medicines' => Medicine::where(function ($query) use ($user): void {
                $query->where('restaurant_id', $user->effectiveRestaurantId())->orWhereNull('restaurant_id');
            })->orderBy('name')->get(),
            'canOrder' => $user->isRestaurantManager() || $user->staff_type === 'doctor',
            'canFulfill' => $user->isRestaurantManager(),
            'canAdminister' => $user->isRestaurantManager() || $user->staff_type === 'nurse',
        ]);
    }

    public function storeOrder(Request $request)
    {
        $user = $this->user();
        abort_unless($user->isRestaurantManager() || $user->staff_type === 'doctor', 403, 'Only managers, owners, and authorized doctors can order medication.');
        $restaurantId = (int) $user->effectiveRestaurantId();

        $admission = HospitalAdmission::where('restaurant_id', $restaurantId)
            ->whereIn('status', ['admitted', 'transferred'])
            ->findOrFail((int) $request->input('hospital_admission_id'));
        abort_unless($this->canViewAdmission($user, $admission), 403, 'You are not authorized for this admission.');

        $data = $request->validate([
            'hospital_admission_id' => ['required', 'integer'],
            'medicine_id' => ['required', 'integer', Rule::exists('medicines', 'id')->where(fn ($query) => $query->where(function ($scope) use ($restaurantId): void {
                $scope->where('restaurant_id', $restaurantId)->orWhereNull('restaurant_id');
            }))],
            'dose' => ['required', 'string', 'max:100'],
            'route' => ['required', 'string', 'max:50'],
            'frequency' => ['nullable', 'string', 'max:100'],
            'scheduled_start_at' => ['required', 'date'],
            'scheduled_end_at' => ['nullable', 'date', 'after_or_equal:scheduled_start_at'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $start = \Carbon\Carbon::parse($data['scheduled_start_at']);
        $this->assertAdmissionWindow($admission, $start, 'The scheduled start time');
        if (! empty($data['scheduled_end_at'])) {
            $this->assertAdmissionWindow($admission, \Carbon\Carbon::parse($data['scheduled_end_at']), 'The scheduled end time');
        }

        HospitalMedicationOrder::create($data + [
            'restaurant_id' => $restaurantId,
            'patient_id' => $admission->patient_id,
            'ordered_by' => $user->id,
            'order_number' => 'MED-' . now()->format('Ymd') . '-' . Str::upper(Str::random(6)),
            'status' => 'active',
            'ordered_at' => now(),
        ]);

        return back()->with('success', 'Inpatient medication order created.');
    }

    public function fulfill(Request $request, HospitalMedicationOrder $order, HospitalMedicationFulfillmentService $service)
    {
        $user = $this->user();
        abort_unless($user->isRestaurantManager(), 403, 'Only managers can fulfill inpatient medication orders.');
        $order = $this->tenantOrder($order, $user);
        abort_unless(in_array($order->status, ['active', 'completed'], true), 422, 'This medication order is not available for fulfillment.');

        $admission = $order->admission()->firstOrFail();
        abort_unless(in_array($admission->status, ['admitted', 'transferred'], true), 422, 'Medication can only be fulfilled for an active admission.');
        $data = $request->validate([
            'quantity' => ['required', 'numeric', 'gt:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $fulfillment = $service->fulfill($order->load('medicine'), (float) $data['quantity'], $user, $data['notes'] ?? null);

        $message = $fulfillment->status === 'partially_dispensed'
            ? 'Medication partially dispensed from available stock. Fulfill the remaining quantity when stock arrives.'
            : 'Medication stock dispensed and pharmacy charge created.';

        return back()->with('success', $message);
    }

    public function storeAdministration(Request $request, HospitalMedicationOrder $order)
    {
        $user = $this->user();
        $order = $this->tenantOrder($order, $user);
        $admission = $order->admission()->firstOrFail();
        abort_unless($this->canRecord($user, $admission), 403, 'Only managers or the assigned nurse can record administration.');
        abort_unless($order->status === 'active', 422, 'This medication order is not active.');

        $data = $this->validateAdministration($request, $order, $admission);
        $existingKey = ! empty($data['idempotency_key'])
            ? HospitalMedicationAdministration::where('restaurant_id', $user->effectiveRestaurantId())
                ->where('idempotency_key', $data['idempotency_key'])->first()
            : null;
        if ($existingKey) {
            abort_unless((int) $existingKey->hospital_medication_order_id === (int) $order->id, 422, 'The idempotency key is already used for another administration.');
            return back()->with('success', 'Administration already recorded.');
        }

        $duplicate = HospitalMedicationAdministration::where('restaurant_id', $user->effectiveRestaurantId())
            ->where('hospital_medication_order_id', $order->id)
            ->where('scheduled_at', \Carbon\Carbon::parse($data['scheduled_at'])->format('Y-m-d H:i:s'))->exists();
        abort_unless(! $duplicate, 422, 'An administration record already exists for this scheduled dose.');

        $this->createAdministration($data, $order, $admission, $user);

        return back()->with('success', 'Medication administration recorded.');
    }

    public function updateAdministration(Request $request, HospitalMedicationAdministration $administration)
    {
        $user = $this->user();
        abort_unless((int) $administration->restaurant_id === (int) $user->effectiveRestaurantId(), 404);
        $order = $administration->order()->firstOrFail();
        $admission = $order->admission()->firstOrFail();
        abort_unless($this->canRecord($user, $admission), 403, 'Only managers or the assigned nurse can update administration.');

        $data = $this->validateAdministration($request, $order, $admission, $administration);
        $administration->update([
            'dose' => $data['dose'],
            'route' => $data['route'],
            'administered_quantity' => $data['administered_quantity'],
            'status' => $data['status'],
            'administered_at' => $data['administered_at'] ?? null,
            'administered_by' => $data['status'] === 'administered' ? $user->id : null,
            'reason' => $data['reason'] ?? null,
            'notes' => $data['notes'] ?? null,
            'recorded_at' => now(),
        ]);

        return back()->with('success', 'Medication administration updated.');
    }

    private function tenantOrder(HospitalMedicationOrder $order, User $user): HospitalMedicationOrder
    {
        abort_unless((int) $order->restaurant_id === (int) $user->effectiveRestaurantId(), 404);

        return $order;
    }

    private function validateAdministration(Request $request, HospitalMedicationOrder $order, HospitalAdmission $admission, ?HospitalMedicationAdministration $existing = null): array
    {
        $data = $request->validate([
            'dose' => ['required', 'string', 'max:100'],
            'route' => ['required', 'string', 'max:50'],
            'administered_quantity' => ['nullable', 'numeric', 'gt:0'],
            'scheduled_at' => ['required', 'date'],
            'administered_at' => ['nullable', 'date'],
            'status' => ['required', Rule::in(['scheduled', 'administered', 'omitted', 'refused', 'cancelled'])],
            'idempotency_key' => ['nullable', 'string', 'max:100'],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $scheduledAt = \Carbon\Carbon::parse($data['scheduled_at']);
        abort_unless($scheduledAt->greaterThanOrEqualTo($order->scheduled_start_at), 422, 'The scheduled time cannot precede the medication order.');
        if ($order->scheduled_end_at) {
            abort_unless($scheduledAt->lessThanOrEqualTo($order->scheduled_end_at), 422, 'The scheduled time cannot exceed the medication order window.');
        }
        $this->assertAdmissionWindow($admission, $scheduledAt, 'The scheduled time');

        if ($data['status'] === 'administered') {
            abort_unless(! empty($data['administered_at']), 422, 'Administered time is required for an administered dose.');
            $administeredAt = \Carbon\Carbon::parse($data['administered_at']);
            abort_unless(! $administeredAt->isFuture(), 422, 'Administered time cannot be in the future.');
            $this->assertAdmissionWindow($admission, $administeredAt, 'The administered time');
            abort_unless($administeredAt->greaterThanOrEqualTo($scheduledAt), 422, 'Administered time cannot precede the scheduled time.');
        } else {
            abort_unless(empty($data['administered_at']), 422, 'Only administered doses can have an administered time.');
        }

        if (in_array($data['status'], ['omitted', 'refused', 'cancelled'], true)) {
            abort_unless(trim((string) ($data['reason'] ?? '')) !== '', 422, 'A reason is required for an omitted, refused, or cancelled dose.');
        }

        if ($existing) {
            abort_unless($scheduledAt->equalTo($existing->scheduled_at), 422, 'The scheduled time cannot be changed after recording.');
        }

        $data['administered_quantity'] = (float) ($data['administered_quantity'] ?? 1);
        if ($data['status'] === 'administered') {
            $fulfillment = $order->fulfillment()->lockForUpdate()->first();
            if ($fulfillment) {
                $alreadyAdministered = HospitalMedicationAdministration::where('hospital_medication_order_id', $order->id)
                    ->where('status', 'administered')
                    ->when($existing, fn ($query) => $query->where('id', '<>', $existing->id))
                    ->sum('administered_quantity');
                $available = max((float) $fulfillment->quantity_dispensed - (float) $alreadyAdministered, 0);
                abort_unless($data['administered_quantity'] <= $available, 422, 'Administration quantity exceeds the dispensed medication remaining.');
            }
        }

        return $data;
    }

    private function createAdministration(array $data, HospitalMedicationOrder $order, HospitalAdmission $admission, User $user): void
    {
        HospitalMedicationAdministration::create($data + [
            'restaurant_id' => $order->restaurant_id,
            'patient_id' => $admission->patient_id,
            'hospital_admission_id' => $admission->id,
            'hospital_medication_order_id' => $order->id,
            'recorded_by' => $user->id,
            'administered_by' => $data['status'] === 'administered' ? $user->id : null,
            'administered_quantity' => $data['administered_quantity'],
            'recorded_at' => now(),
        ]);
    }
}
