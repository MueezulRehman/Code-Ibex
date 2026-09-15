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

    private function scopeToCurrentTenant(HospitalBilling $billing, User $user): HospitalBilling
    {
        abort_unless($billing->restaurant_id === (int) $user->effectiveRestaurantId(), 404);
        return $billing;
    }

    public function index(Request $request)
    {
        $user = $this->user();
        $restaurantId = (int) $user->effectiveRestaurantId();
        $admissionIds = $user->staff_type === 'nurse' && ! $user->isRestaurantManager()
            ? $user->nursingAssignments()->where('status', 'active')->pluck('hospital_admission_id') : null;

        $status = $request->query('status');
        $admissionId = $request->query('hospital_admission_id');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        $billings = HospitalBilling::with(['patient', 'admission', 'postedBy', 'paidBy'])
            ->where('restaurant_id', $restaurantId)
            ->when($admissionIds !== null, fn ($q) => $q->whereIn('hospital_admission_id', $admissionIds))
            ->when($status && $status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($admissionId, fn ($q) => $q->where('hospital_admission_id', $admissionId))
            ->when($dateFrom, fn ($q) => $q->whereDate('charged_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('charged_at', '<=', $dateTo))
            ->latest('charged_at')->paginate(20)->appends($request->query());

        return view('manager.hospital-billing.index', [
            'billings' => $billings,
            'admissions' => HospitalAdmission::with('patient')
                ->where('restaurant_id', $restaurantId)
                ->whereIn('status', ['admitted', 'transferred'])
                ->when($admissionIds !== null, fn ($q) => $q->whereIn('id', $admissionIds))
                ->orderBy('admitted_at')->get(),
            'canManage' => $user->isRestaurantManager(),
            'reconciliationSummary' => HospitalBilling::tenantSummary($restaurantId),
            'filterStatus' => $status ?: 'all',
            'filterAdmissionId' => $admissionId,
            'filterDateFrom' => $dateFrom,
            'filterDateTo' => $dateTo,
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
        $user = $this->manage();
        $billing = $this->scopeToCurrentTenant($billing, $user);
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
        $billing = $this->scopeToCurrentTenant($billing, $user);
        abort_unless($billing->status === 'draft', 422, 'Only draft charges can be posted.');
        $billing->update(['status' => 'posted', 'posted_by' => $user->id, 'posted_at' => now()]);
        return back()->with('success', 'Hospital billing charge posted.');
    }

    public function pay(Request $request, HospitalBilling $billing)
    {
        $user = $this->manage();
        $billing = $this->scopeToCurrentTenant($billing, $user);
        abort_unless($billing->status === 'posted', 422, 'Only posted charges can be paid.');
        abort_if($billing->total_amount <= 0, 422, 'Only positive charges can be marked paid.');

        $data = $request->validate([
            'payment_reference' => ['required', 'string', 'max:255'],
            'payment_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $billing->update([
            'status' => 'paid',
            'paid_by' => $user->id,
            'paid_at' => now(),
            'payment_reference' => trim($data['payment_reference']),
            'payment_notes' => $data['payment_notes'] ?? null,
        ]);
        return back()->with('success', 'Hospital billing charge paid.');
    }

    public function void(HospitalBilling $billing)
    {
        $user = $this->manage();
        $billing = $this->scopeToCurrentTenant($billing, $user);
        abort_unless(in_array($billing->status, ['draft', 'posted'], true), 422, 'Paid or void charges cannot be voided.');
        $billing->update([
            'status' => 'void',
            'payment_reference' => $billing->payment_reference ?: null,
            'payment_notes' => $billing->payment_notes ?: null,
        ]);
        return back()->with('success', 'Hospital billing charge voided.');
    }
}
