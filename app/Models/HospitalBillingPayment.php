<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class HospitalBillingPayment extends Model
{
    use BelongsToRestaurant;

    protected $fillable = [
        'restaurant_id',
        'hospital_billing_id',
        'hospital_admission_id',
        'type',
        'amount',
        'method',
        'reference',
        'notes',
        'recorded_by',
        'recorded_at',
        'status',
        'voided_by',
        'voided_at',
        'void_reason',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'recorded_at' => 'datetime',
        'voided_at' => 'datetime',
    ];

    public function billing()
    {
        return $this->belongsTo(HospitalBilling::class, 'hospital_billing_id');
    }

    public function admission()
    {
        return $this->belongsTo(HospitalAdmission::class, 'hospital_admission_id');
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function voidedBy()
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function isActive(): bool
    {
        return $this->status === 'posted';
    }

    protected static function booted(): void
    {
        static::creating(function (self $payment): void {
            $amount = round((float) $payment->amount, 2);
            if ($amount <= 0) {
                throw ValidationException::withMessages(['amount' => 'Payment amount must be positive.']);
            }

            $billing = HospitalBilling::withoutGlobalScopes()->findOrFail($payment->hospital_billing_id);
            if ((int) $billing->restaurant_id !== (int) $payment->restaurant_id
                || (int) $billing->hospital_admission_id !== (int) $payment->hospital_admission_id) {
                throw ValidationException::withMessages(['hospital_billing_id' => 'Payment does not belong to this tenant admission charge.']);
            }
            if (! in_array($payment->type, ['payment', 'refund'], true)) {
                throw ValidationException::withMessages(['type' => 'Payment type is invalid.']);
            }
            if (! in_array($billing->status, ['posted', 'paid'], true)) {
                throw ValidationException::withMessages(['status' => 'Only posted or paid charges can have ledger entries.']);
            }

            $settled = $billing->settledAmount();
            if ($payment->type === 'payment' && $amount > $billing->outstandingAmount()) {
                throw ValidationException::withMessages(['amount' => 'Payment cannot exceed the outstanding balance.']);
            }
            if ($payment->type === 'refund' && $amount > $settled) {
                throw ValidationException::withMessages(['amount' => 'Refund cannot exceed the settled payment balance.']);
            }
        });
    }
}
