<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class HospitalBilling extends Model
{
    use BelongsToRestaurant;

    protected $fillable = [
        'restaurant_id', 'patient_id', 'hospital_admission_id', 'charge_type',
        'description', 'quantity', 'unit_amount', 'total_amount', 'status',
        'charged_at', 'posted_by', 'paid_by', 'posted_at', 'paid_at', 'notes',
        'payment_reference', 'payment_notes', 'invoice_number', 'receipt_number',
        'payment_method', 'receipt_issued_at', 'insurance_claim_id', 'hospital_patient_insurance_id',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'charged_at' => 'datetime',
        'posted_at' => 'datetime',
        'paid_at' => 'datetime',
        'receipt_issued_at' => 'datetime',
    ];

    public static function tenantSummary(?int $restaurantId = null): array
    {
        $query = static::with('payments');

        if ($restaurantId !== null) {
            $query->where('restaurant_id', $restaurantId);
        }

        $billings = $query->get(['id', 'status', 'total_amount']);

        $sum = function (array $statuses) use ($billings): float {
            return (float) $billings
                ->whereIn('status', $statuses)
                ->sum('total_amount');
        };

        $gross = $sum(['draft', 'posted', 'paid', 'void']);
        $posted = $sum(['posted']);
        $paid = (float) $billings->sum(fn (self $billing): float => $billing->settledAmount());
        $outstanding = (float) $billings
            ->where('status', 'posted')
            ->sum(fn (self $billing): float => $billing->outstandingAmount());
        $draft = $sum(['draft']);
        $void = $sum(['void']);

        return [
            'gross' => $gross,
            'posted' => $posted,
            'paid' => $paid,
            'outstanding' => $outstanding,
            'draft' => $draft,
            'void' => $void,
        ];
    }

    public function patient() { return $this->belongsTo(Patient::class); }
    public function admission() { return $this->belongsTo(HospitalAdmission::class, 'hospital_admission_id'); }
    public function payments() { return $this->hasMany(HospitalBillingPayment::class, 'hospital_billing_id'); }
    public function postedBy() { return $this->belongsTo(User::class, 'posted_by'); }
    public function paidBy() { return $this->belongsTo(User::class, 'paid_by'); }
    public function insuranceClaim() { return $this->belongsTo(InsuranceClaim::class, 'insurance_claim_id'); }
    public function insurancePolicy() { return $this->belongsTo(HospitalPatientInsurance::class, 'hospital_patient_insurance_id'); }

    public function canAcceptInsurancePayment(): bool
    {
        return ! empty($this->hospital_patient_insurance_id) || ! empty($this->insurance_claim_id);
    }

    public function insurerApprovedAmount(): float
    {
        if (! $this->canAcceptInsurancePayment()) {
            return 0.0;
        }

        $approved = $this->insuranceClaim?->approved_amount ?? null;
        if (is_numeric($approved) && (float) $approved > 0) {
            return min(round((float) $approved, 2), round((float) $this->total_amount, 2));
        }

        return round((float) $this->total_amount, 2);
    }

    public function insurerSettledAmount(): float
    {
        $payments = $this->payments()->where('status', 'posted');
        $insurancePayments = (float) (clone $payments)->where('type', 'payment')->where('method', 'insurance')->sum('amount');
        $insuranceRefunds = (float) (clone $payments)->where('type', 'refund')->where('method', 'insurance')->sum('amount');

        return round($insurancePayments - $insuranceRefunds, 2);
    }

    public function patientResidualAmount(): float
    {
        return max(round((float) $this->total_amount - $this->insurerApprovedAmount(), 2), 0.0);
    }

    public function patientSettledAmount(): float
    {
        return max(round($this->settledAmount() - $this->insurerSettledAmount(), 2), 0.0);
    }

    public function remainingInsuranceCoverage(): float
    {
        return max(round($this->insurerApprovedAmount() - $this->insurerSettledAmount(), 2), 0.0);
    }

    public function remainingPatientBalance(): float
    {
        return max(round($this->patientResidualAmount() - $this->patientSettledAmount(), 2), 0.0);
    }

    public function settlementPrecedence(): array
    {
        return [
            'precedence' => 'insurance_first_then_patient',
            'requires_linked_policy_for_insurance' => true,
            'has_linked_policy' => $this->canAcceptInsurancePayment(),
            'insurer_approved_amount' => $this->insurerApprovedAmount(),
            'patient_residual_amount' => $this->patientResidualAmount(),
            'remaining_insurance_coverage' => $this->remainingInsuranceCoverage(),
            'remaining_patient_balance' => $this->remainingPatientBalance(),
        ];
    }

    public function settledAmount(): float
    {
        if ($this->relationLoaded('payments')) {
            $payments = $this->payments->where('status', 'posted');
            $hasPaymentRow = $this->payments->contains('type', 'payment');
            if (! $hasPaymentRow && $this->status === 'paid') {
                return max(round((float) $this->total_amount - (float) $payments->where('type', 'refund')->sum('amount'), 2), 0.0);
            }
            return round((float) $payments->where('type', 'payment')->sum('amount')
                - (float) $payments->where('type', 'refund')->sum('amount'), 2);
        }

        $payments = $this->payments()->where('status', 'posted');
        $payment = (float) (clone $payments)->where('type', 'payment')->sum('amount');
        $refund = (float) (clone $payments)->where('type', 'refund')->sum('amount');

        if (! $this->payments()->where('type', 'payment')->exists() && $this->status === 'paid') {
            return max(round((float) $this->total_amount - $refund, 2), 0.0);
        }

        return round($payment - $refund, 2);
    }

    public function outstandingAmount(): float
    {
        return max(round((float) $this->total_amount - $this->settledAmount(), 2), 0.0);
    }

    protected static function booted(): void
    {
        static::creating(function (self $billing): void {
            if (! $billing->invoice_number && $billing->restaurant_id) {
                $billing->invoice_number = static::nextDocumentNumber('INV', (int) $billing->restaurant_id);
            }
        });

        static::saving(function (self $billing): void {
            if (is_null($billing->quantity) || is_null($billing->unit_amount)) {
                return;
            }

            $billing->total_amount = round((float) $billing->quantity * (float) $billing->unit_amount, 2);
        });
    }

    public static function nextDocumentNumber(string $prefix, int $restaurantId): string
    {
        do {
            $number = sprintf(
                '%s-%d-%s-%s',
                $prefix,
                $restaurantId,
                now()->format('Ymd'),
                Str::upper(Str::random(8))
            );
        } while (static::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where(function ($query) use ($prefix, $number): void {
                $column = $prefix === 'RCT' ? 'receipt_number' : 'invoice_number';
                $query->where($column, $number);
            })
            ->exists());

        return $number;
    }
}
