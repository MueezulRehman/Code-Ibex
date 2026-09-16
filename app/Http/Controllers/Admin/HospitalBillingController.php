<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HospitalAdmission;
use App\Models\HospitalBilling;
use App\Models\HospitalBillingPayment;
use App\Models\HospitalPatientInsurance;
use App\Models\InsuranceClaim;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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

    private function syncSettlement(HospitalBilling $billing, User $user): void
    {
        $settled = $billing->settledAmount();
        if ($settled >= (float) $billing->total_amount) {
            $updates = ['status' => 'paid', 'paid_by' => $billing->paid_by ?: $user->id, 'paid_at' => $billing->paid_at ?: now()];
            if (! $billing->receipt_number) {
                $updates['receipt_number'] = HospitalBilling::nextDocumentNumber('RCT', (int) $user->effectiveRestaurantId());
                $updates['receipt_issued_at'] = now();
            }
            $billing->update($updates);
        } elseif ($billing->status === 'paid') {
            $billing->update(['status' => 'posted']);
        }
    }

    private function insuranceClaimRule(int $restaurantId): array
    {
        return [
            'nullable',
            'integer',
            Rule::exists('insurance_claims', 'id')
                ->where(fn ($q) => $q->where('restaurant_id', $restaurantId)),
        ];
    }

    private function assertInsuranceClaimMatchesAdmission(?int $claimId, HospitalAdmission $admission, int $restaurantId): void
    {
        if (! $claimId) {
            return;
        }

        $claim = InsuranceClaim::where('restaurant_id', $restaurantId)->findOrFail($claimId);
        if (Schema::hasColumn('insurance_claims', 'hospital_patient_insurance_id') && $claim->hospital_patient_insurance_id) {
            $policy = HospitalPatientInsurance::where('restaurant_id', $restaurantId)
                ->findOrFail($claim->hospital_patient_insurance_id);
            abort_unless((int) $policy->patient_id === (int) $admission->patient_id, 422, 'Insurance claim policy does not belong to this patient.');
            if ($admission->hospital_patient_insurance_id) {
                abort_unless((int) $policy->id === (int) $admission->hospital_patient_insurance_id, 422, 'Insurance claim policy does not match this admission.');
            }
        }
        foreach (['patient_id' => (int) $admission->patient_id, 'hospital_admission_id' => (int) $admission->id] as $column => $expected) {
            if (Schema::hasColumn('insurance_claims', $column) && $claim->{$column} !== null) {
                abort_unless((int) $claim->{$column} === $expected, 422, "Insurance claim {$column} does not match this admission.");
            }
        }

    }

    private function hospitalInsuranceRule(int $restaurantId): array
    {
        return [
            'nullable',
            'integer',
            Rule::exists('hospital_patient_insurances', 'id')
                ->where(fn ($q) => $q->where('restaurant_id', $restaurantId)),
        ];
    }

    private function assertHospitalInsuranceMatchesAdmission(?int $policyId, HospitalAdmission $admission, int $restaurantId): void
    {
        if (! $policyId) {
            return;
        }

        $policy = HospitalPatientInsurance::where('restaurant_id', $restaurantId)->findOrFail($policyId);
        abort_unless((int) $policy->patient_id === (int) $admission->patient_id, 422, 'Insurance policy does not belong to this admission patient.');
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
        $chargeType = $request->query('charge_type');
        $paymentMethod = $request->query('payment_method');
        $insuranceLinked = $request->query('insurance_linked');

        $billings = HospitalBilling::with(['patient', 'admission', 'postedBy', 'paidBy', 'payments', 'insuranceClaim', 'insurancePolicy'])
            ->where('restaurant_id', $restaurantId)
            ->when($admissionIds !== null, fn ($q) => $q->whereIn('hospital_admission_id', $admissionIds))
            ->when($status && $status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($admissionId, fn ($q) => $q->where('hospital_admission_id', $admissionId))
            ->when($chargeType && $chargeType !== 'all', fn ($q) => $q->where('charge_type', $chargeType))
            ->when($paymentMethod && $paymentMethod !== 'all', fn ($q) => $q->where('payment_method', $paymentMethod))
            ->when($insuranceLinked === 'linked', fn ($q) => $q->whereNotNull('insurance_claim_id'))
            ->when($insuranceLinked === 'unlinked', fn ($q) => $q->whereNull('insurance_claim_id'))
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
            'filterChargeType' => $chargeType ?: 'all',
            'filterPaymentMethod' => $paymentMethod ?: 'all',
            'filterInsuranceLinked' => $insuranceLinked ?: 'all',
            'insuranceClaims' => InsuranceClaim::where('restaurant_id', $restaurantId)->latest('id')->get(),
            'patientInsurances' => HospitalPatientInsurance::where('restaurant_id', $restaurantId)->with('patient')->latest('id')->get(),
        ]);
    }

    public function report(Request $request)
    {
        $user = $this->manage();
        $restaurantId = (int) $user->effectiveRestaurantId();
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['all', 'draft', 'posted', 'paid', 'void'])],
            'charge_type' => ['nullable', Rule::in(['all', 'admission', 'bed', 'nursing', 'laboratory', 'radiology', 'surgery', 'medication', 'other'])],
            'payment_method' => ['nullable', Rule::in(['all', 'cash', 'card', 'bank_transfer', 'insurance', 'other'])],
            'hospital_admission_id' => ['nullable', 'integer', Rule::exists('hospital_admissions', 'id')->where(fn ($q) => $q->where('restaurant_id', $restaurantId))],
            'insurance_linked' => ['nullable', Rule::in(['all', 'linked', 'unlinked'])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $billings = HospitalBilling::with(['patient', 'admission', 'payments', 'insuranceClaim'])
            ->where('restaurant_id', $restaurantId)
            ->when(($filters['status'] ?? null) && $filters['status'] !== 'all', fn ($q) => $q->where('status', $filters['status']))
            ->when(($filters['charge_type'] ?? null) && $filters['charge_type'] !== 'all', fn ($q) => $q->where('charge_type', $filters['charge_type']))
            ->when(($filters['payment_method'] ?? null) && $filters['payment_method'] !== 'all', fn ($q) => $q->where('payment_method', $filters['payment_method']))
            ->when($filters['hospital_admission_id'] ?? null, fn ($q) => $q->where('hospital_admission_id', $filters['hospital_admission_id']))
            ->when(($filters['insurance_linked'] ?? null) === 'linked', fn ($q) => $q->whereNotNull('insurance_claim_id'))
            ->when(($filters['insurance_linked'] ?? null) === 'unlinked', fn ($q) => $q->whereNull('insurance_claim_id'))
            ->when($filters['date_from'] ?? null, fn ($q) => $q->whereDate('charged_at', '>=', $filters['date_from']))
            ->when($filters['date_to'] ?? null, fn ($q) => $q->whereDate('charged_at', '<=', $filters['date_to']))
            ->latest('charged_at')->get();

        $group = static function ($rows): array {
            return [
                'count' => $rows->count(),
                'amount' => (float) $rows->sum('total_amount'),
            ];
        };
        $groupBy = static function ($rows, callable $key) use ($group): array {
            return $rows->groupBy($key)->map($group)->sortKeys()->all();
        };
        $statusTotals = $groupBy($billings, fn ($billing) => $billing->status);
        $chargeTypeTotals = $groupBy($billings, fn ($billing) => $billing->charge_type);
        $dateTotals = $groupBy($billings, fn ($billing) => $billing->charged_at?->format('Y-m-d') ?: 'unknown');
        $admissionTotals = $groupBy($billings, fn ($billing) => $billing->admission?->admission_number ?: (string) $billing->hospital_admission_id);
        $paymentMethodTotals = $groupBy($billings, fn ($billing) => $billing->payment_method ?: 'unrecorded');
        $totals = [
            'gross' => (float) $billings->sum('total_amount'),
            'settled' => (float) $billings->sum(fn ($billing) => $billing->settledAmount()),
            'refunded' => (float) $billings->sum(fn ($billing) => $billing->payments->where('status', 'posted')->where('type', 'refund')->sum('amount')),
            'outstanding' => (float) $billings->where('status', 'posted')->sum(fn ($billing) => $billing->outstandingAmount()),
            'insurance_linked' => (float) $billings->whereNotNull('insurance_claim_id')->sum('total_amount'),
            'insurance_outstanding' => (float) $billings->whereNotNull('insurance_claim_id')->where('status', 'posted')->sum(fn ($billing) => $billing->outstandingAmount()),
        ];
        $totals['insurance_linked_amount'] = $totals['insurance_linked'];
        $totals['insurance_linked_outstanding'] = $totals['insurance_outstanding'];

        if ($request->query('format') === 'csv') {
            return response()->streamDownload(function () use ($billings): void {
                $output = fopen('php://output', 'w');
                fputcsv($output, ['Invoice', 'Status', 'Charged date', 'Admission', 'Patient', 'Charge type', 'Payment method', 'Insurance claim', 'Amount', 'Settled', 'Outstanding']);
                foreach ($billings as $billing) {
                    fputcsv($output, [
                        $billing->invoice_number,
                        $billing->status,
                        $billing->charged_at?->format('Y-m-d H:i:s'),
                        $billing->admission?->admission_number,
                        $billing->patient?->name,
                        $billing->charge_type,
                        $billing->payment_method ?: 'unrecorded',
                        $billing->insuranceClaim?->claim_number ?: ($billing->insurance_claim_id ? '#' . $billing->insurance_claim_id : ''),
                        number_format((float) $billing->total_amount, 2, '.', ''),
                        number_format($billing->settledAmount(), 2, '.', ''),
                        number_format($billing->outstandingAmount(), 2, '.', ''),
                    ]);
                }
                fclose($output);
            }, 'hospital-billing-report.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        return view('manager.hospital-billing.report', compact(
            'billings', 'statusTotals', 'chargeTypeTotals', 'dateTotals',
            'admissionTotals', 'paymentMethodTotals', 'totals', 'filters'
        ) + [
            'admissions' => HospitalAdmission::where('restaurant_id', $restaurantId)
                ->with('patient')->orderByDesc('admitted_at')->get(),
        ]);
    }

    public function exportReport(Request $request)
    {
        $request->merge(['format' => 'csv']);
        return $this->report($request);
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
            'insurance_claim_id' => $this->insuranceClaimRule($restaurantId),
            'hospital_patient_insurance_id' => $this->hospitalInsuranceRule($restaurantId),
        ]);
        $admission = HospitalAdmission::where('restaurant_id', $restaurantId)->findOrFail($data['hospital_admission_id']);
        $this->assertInsuranceClaimMatchesAdmission($data['insurance_claim_id'] ?? null, $admission, $restaurantId);
        $this->assertHospitalInsuranceMatchesAdmission($data['hospital_patient_insurance_id'] ?? null, $admission, $restaurantId);
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
            'insurance_claim_id' => $this->insuranceClaimRule((int) $user->effectiveRestaurantId()),
            'hospital_patient_insurance_id' => $this->hospitalInsuranceRule((int) $user->effectiveRestaurantId()),
        ]);
        $this->assertInsuranceClaimMatchesAdmission(
            $data['insurance_claim_id'] ?? null,
            $billing->admission()->firstOrFail(),
            (int) $user->effectiveRestaurantId()
        );
        $this->assertHospitalInsuranceMatchesAdmission(
            $data['hospital_patient_insurance_id'] ?? null,
            $billing->admission()->firstOrFail(),
            (int) $user->effectiveRestaurantId()
        );
        $billing->update($data);
        return back()->with('success', 'Hospital billing charge updated.');
    }

    public function linkInsuranceClaim(Request $request, HospitalBilling $billing)
    {
        $user = $this->manage();
        $billing = $this->scopeToCurrentTenant($billing, $user);
        abort_unless($billing->status !== 'void', 422, 'Void charges cannot be linked to an insurance claim.');
        $data = $request->validate([
            'insurance_claim_id' => $this->insuranceClaimRule((int) $user->effectiveRestaurantId()),
            'hospital_patient_insurance_id' => $this->hospitalInsuranceRule((int) $user->effectiveRestaurantId()),
        ]);
        $admission = $billing->admission()->firstOrFail();
        $this->assertInsuranceClaimMatchesAdmission(
            $data['insurance_claim_id'] ?? null,
            $admission,
            (int) $user->effectiveRestaurantId()
        );
        $this->assertHospitalInsuranceMatchesAdmission(
            $data['hospital_patient_insurance_id'] ?? null,
            $admission,
            (int) $user->effectiveRestaurantId()
        );
        $billing->update([
            'insurance_claim_id' => $data['insurance_claim_id'] ?? null,
            'hospital_patient_insurance_id' => $data['hospital_patient_insurance_id'] ?? null,
        ]);
        return back()->with('success', 'Insurance claim linkage updated.');
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

        $data = $request->validate([
            'amount' => ['nullable', 'numeric', 'gt:0'],
            'payment_reference' => ['required_without:reference', 'nullable', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:255'],
            'method' => ['nullable', Rule::in(['cash', 'card', 'bank_transfer', 'insurance', 'other'])],
            'notes' => ['nullable', 'string', 'max:5000'],
            'payment_method' => ['nullable', Rule::in(['cash', 'card', 'bank_transfer', 'insurance', 'other'])],
            'payment_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $reference = trim($data['payment_reference'] ?? $data['reference'] ?? '');
        abort_unless($reference !== '', 422, 'A payment reference is required.');

        $duplicate = false;
        DB::transaction(function () use ($billing, $data, $reference, $user, &$duplicate): void {
            $billing = HospitalBilling::where('restaurant_id', $user->effectiveRestaurantId())
                ->whereKey($billing->id)->lockForUpdate()->firstOrFail();
            abort_unless($billing->status === 'posted', 422, 'Only posted charges can be paid.');
            if (HospitalBillingPayment::where('restaurant_id', $billing->restaurant_id)
                ->where('hospital_billing_id', $billing->id)
                ->where('type', 'payment')
                ->where('reference', $reference)
                ->where('status', 'posted')
                ->exists()) {
                $duplicate = true;
                return;
            }
            $amount = round((float) ($data['amount'] ?? $billing->outstandingAmount()), 2);
            abort_if($amount <= 0, 422, 'This charge has no outstanding balance.');
            abort_if($amount > $billing->outstandingAmount(), 422, 'Payment cannot exceed the outstanding balance.');

            HospitalBillingPayment::create([
                'restaurant_id' => $billing->restaurant_id,
                'hospital_billing_id' => $billing->id,
                'hospital_admission_id' => $billing->hospital_admission_id,
                'type' => 'payment',
                'amount' => $amount,
                'method' => $data['payment_method'] ?? $data['method'] ?? 'other',
                'reference' => $reference,
                'notes' => $data['payment_notes'] ?? $data['notes'] ?? null,
                'recorded_by' => $user->id,
                'recorded_at' => now(),
                'status' => 'posted',
            ]);

            $billing->load('payments');
            $billing->update([
                'payment_reference' => $reference,
                'payment_method' => $data['payment_method'] ?? $data['method'] ?? 'other',
                'payment_notes' => $data['payment_notes'] ?? $data['notes'] ?? null,
            ]);
            $this->syncSettlement($billing, $user);
        });

        return back()->with('success', $duplicate
            ? 'This payment reference was already recorded; no duplicate payment was created.'
            : 'Hospital billing payment recorded.');
    }

    public function refund(Request $request, HospitalBilling $billing)
    {
        $user = $this->manage();
        $billing = $this->scopeToCurrentTenant($billing, $user);
        abort_unless(in_array($billing->status, ['posted', 'paid'], true), 422, 'Only settled charges can be refunded.');
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'reference' => ['nullable', 'string', 'max:255'],
            'payment_reference' => ['nullable', 'string', 'max:255'],
            'method' => ['nullable', Rule::in(['cash', 'card', 'bank_transfer', 'insurance', 'other'])],
            'notes' => ['nullable', 'string', 'max:5000'],
            'payment_method' => ['nullable', Rule::in(['cash', 'card', 'bank_transfer', 'insurance', 'other'])],
            'payment_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        DB::transaction(function () use ($billing, $data, $user): void {
            $billing = HospitalBilling::with('payments')->where('restaurant_id', $user->effectiveRestaurantId())
                ->whereKey($billing->id)->lockForUpdate()->firstOrFail();
            $settled = $billing->settledAmount();
            $amount = round((float) $data['amount'], 2);
            abort_if($settled <= 0, 422, 'This charge has no settled payment to refund.');
            abort_if($amount > $settled, 422, 'Refund cannot exceed the settled payment balance.');

            HospitalBillingPayment::create([
                'restaurant_id' => $billing->restaurant_id,
                'hospital_billing_id' => $billing->id,
                'hospital_admission_id' => $billing->hospital_admission_id,
                'type' => 'refund',
                'amount' => $amount,
                'method' => $data['payment_method'] ?? $data['method'] ?? $billing->payment_method ?? 'other',
                'reference' => $data['reference'] ?? $data['payment_reference'] ?? null,
                'notes' => $data['payment_notes'] ?? $data['notes'] ?? null,
                'recorded_by' => $user->id,
                'recorded_at' => now(),
                'status' => 'posted',
            ]);
            $billing->load('payments');
            $this->syncSettlement($billing, $user);
        });

        return back()->with('success', 'Hospital billing refund recorded.');
    }

    public function voidPayment(Request $request, HospitalBilling $billing, HospitalBillingPayment $payment)
    {
        $user = $this->manage();
        $data = $request->validate(['void_reason' => ['nullable', 'string', 'max:1000']]);
        $billing = $this->scopeToCurrentTenant($billing, $user);
        abort_unless((int) $payment->hospital_billing_id === (int) $billing->id
            && (int) $payment->restaurant_id === (int) $user->effectiveRestaurantId(), 404);
        abort_unless($payment->status === 'posted', 422, 'Only posted payment records can be voided.');

        DB::transaction(function () use ($billing, $payment, $user, $data): void {
            $billing = HospitalBilling::with('payments')->where('restaurant_id', $user->effectiveRestaurantId())
                ->whereKey($billing->id)->lockForUpdate()->firstOrFail();
            $payment = HospitalBillingPayment::where('restaurant_id', $user->effectiveRestaurantId())
                ->where('hospital_billing_id', $billing->id)->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            abort_unless($payment->status === 'posted', 422, 'Only posted payment records can be voided.');
            $payment->update([
                'status' => 'void',
                'voided_by' => $user->id,
                'voided_at' => now(),
                'void_reason' => $data['void_reason'] ?? null,
            ]);
            $billing->load('payments');
            $this->syncSettlement($billing, $user);
        });

        return back()->with('success', 'Hospital billing payment record voided.');
    }

    public function receipt(HospitalBilling $billing)
    {
        $user = $this->manage();
        $billing = $this->scopeToCurrentTenant($billing, $user);
        abort_unless($billing->status === 'paid', 422, 'Payment receipts are available only for paid charges.');

        return view('manager.hospital-billing.receipt', [
            'billing' => $billing->load(['patient', 'admission', 'paidBy', 'payments.recordedBy']),
        ]);
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
