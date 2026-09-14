<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HospitalAdmission;
use App\Models\LabOrder;
use App\Models\LabResult;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        return $user->isRestaurantManager()
            || $user->staff_type === 'doctor'
            || ($user->staff_type === 'nurse' && $user->nursingAssignments()
                ->where('hospital_admission_id', $admission->id)
                ->where('status', 'active')->exists());
    }

    public function index()
    {
        $user = $this->user();
        $admissionIds = $user->staff_type === 'nurse' && ! $user->isRestaurantManager()
            ? $user->nursingAssignments()->where('status', 'active')->pluck('hospital_admission_id')
            : null;

        $orders = LabOrder::with(['patient', 'admission', 'orderedBy', 'result'])
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
        $data = $request->validate([
            'hospital_admission_id' => ['required', 'integer', Rule::exists('hospital_admissions', 'id')->where(fn ($q) => $q->where('restaurant_id', $restaurantId)->whereIn('status', ['admitted', 'transferred']))],
            'test_name' => ['required', 'string', 'max:255'],
            'priority' => ['required', Rule::in(['routine', 'urgent', 'stat'])],
            'ordered_at' => ['required', 'date'],
            'clinical_notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $admission = HospitalAdmission::where('restaurant_id', $restaurantId)->findOrFail($data['hospital_admission_id']);
        LabOrder::create($data + [
            'restaurant_id' => $restaurantId,
            'patient_id' => $admission->patient_id,
            'ordered_by' => $user->id,
            'order_number' => 'LAB-' . now()->format('Ymd') . '-' . Str::upper(Str::random(5)),
            'status' => 'ordered',
        ]);

        return back()->with('success', 'Laboratory order created.');
    }

    public function storeResult(Request $request, LabOrder $order)
    {
        $user = $this->user();
        abort_unless($user->isRestaurantManager(), 403, 'Only managers and owners can manage laboratory results.');
        abort_unless($order->status !== 'cancelled' && ! $order->result()->exists(), 422, 'This laboratory order already has a result.');
        $data = $request->validate([
            'result_value' => ['required', 'string', 'max:10000'],
            'resulted_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::in(['entered', 'verified'])],
        ]);
        LabResult::create($data + [
            'restaurant_id' => $order->restaurant_id,
            'lab_order_id' => $order->id,
            'patient_id' => $order->patient_id,
            'hospital_admission_id' => $order->hospital_admission_id,
            'entered_by' => $user->id,
            'verified_by' => $data['status'] === 'verified' ? $user->id : null,
            'verified_at' => $data['status'] === 'verified' ? now() : null,
        ]);
        $order->update(['status' => $data['status'] === 'verified' ? 'resulted' : 'in_progress']);

        return back()->with('success', 'Laboratory result recorded.');
    }
}
