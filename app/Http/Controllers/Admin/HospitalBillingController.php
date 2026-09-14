<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HospitalAdmission;
use App\Models\HospitalBilling;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class HospitalBillingController extends Controller
{
    private function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $user->effectiveRestaurantId(), 403);
        abort_unless($user->isRestaurantManager() || in_array($user->staff_type, ['doctor', 'nurse'], true), 403);
        return $user;
    }

    private function canView(User $user, HospitalBilling $billing): bool
    {
        return $user->isRestaurantManager()
            || $user->staff_type === 'doctor'
            || ($user->staff_type === 'nurse' && $user->nursingAssignments()
                ->where('hospital_admission_id', $billing->hospital_admission_id)
                ->where('status', 'active')->exists());
    }

    private function manage(): User
    {
        $user = $this->user();
        abort_unless($user->isRestaurantManager(), 403, 'Only managers and owners can manage billing.');
        return $user;
    }

    public function index()
    {
        $user = $this->user();
        $admissionIds = $user->staff_type === 'nurse' && ! $user->isRestaurantManager()
            ? $user->nursingAssignments()->where('status', 'active')->pluck('hospital_admission_id') : null;

        $billings = HospitalBilling::with(['patient', 'admission', 'postedBy', 'paidBy'])
            ->when($admissionIds !== null, fn ($q) => $q->whereIn('hospital_admission_id', $admissionIds))
            ->latest('charged_at')->paginate(20);

        return view('manager.hospital-billing.index', [
            'billings' => $billings,
            'admissions' => HospitalAdmission::with('patient')
                ->whereIn('status', ['admitted', 'transferred'])
                ->when($admissionIds !== null, fn ($q) => $q->whereIn('id', $admissionIds))
                ->orderBy('admitted_at')->get(),
            'canManage' => $user->isRestaurantManager(),
        ]);
    }

    public function store(Request $request)
    {
        $user = $this->manage();
        $restaurantId = (int) $user->effectiveRestaurantId();
        $data = $request->validate([
            'hospital_admission_id' => ['required', 'integer', Rule::exists('hospital_admissions', 'id')->where(fn ($q) => $q->where('restaurant_id', $restaurantId))],
            'charge_type' => ['required', Rule::in(['admission', 'bed', 'nursing', 'laboratory', 'radiology', 'surgery', 'medication', 'other'])],
            'description' => ['required', 'string', 'max:1000'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit_amount' => ['required', 'numeric', 'gt:0'],
            'charged_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $admission = HospitalAdmission::where('restaurant_id', $restaurantId)->findOrFail($data['hospital_admission_id']);
        HospitalBilling::create($data + ['restaurant_id' => $restaurantId, 'patient_id' => $admission->patient_id, 'status' => 'draft']);
        return back()->with('success', 'Hospital billing charge created.');
    }

    public function update(Request $request, HospitalBilling $billing)
    {
        $this->manage();
        abort_unless($billing->status === 'draft', 422, 'Only draft charges can be updated.');
        $data = $request->validate([
            'charge_type' => ['required', Rule::in(['admission', 'bed', 'nursing', 'laboratory', 'radiology', 'surgery', 'medication', 'other'])],
            'description' => ['required', 'string', 'max:1000'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit_amount' => ['required', 'numeric', 'gt:0'],
            'charged_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $billing->update($data);
        return back()->with('success', 'Hospital billing charge updated.');
    }

    public function post(HospitalBilling $billing)
    {
        $user = $this->manage();
        abort_unless($billing->status === 'draft', 422, 'Only draft charges can be posted.');
        $billing->update(['status' => 'posted', 'posted_by' => $user->id, 'posted_at' => now()]);
        return back()->with('success', 'Hospital billing charge posted.');
    }

    public function pay(HospitalBilling $billing)
    {
        $user = $this->manage();
        abort_unless($billing->status === 'posted', 422, 'Only posted charges can be paid.');
        $billing->update(['status' => 'paid', 'paid_by' => $user->id, 'paid_at' => now()]);
        return back()->with('success', 'Hospital billing charge paid.');
    }

    public function void(HospitalBilling $billing)
    {
        $this->manage();
        abort_unless(in_array($billing->status, ['draft', 'posted'], true), 422, 'Paid or void charges cannot be voided.');
        $billing->update(['status' => 'void']);
        return back()->with('success', 'Hospital billing charge voided.');
    }
}
