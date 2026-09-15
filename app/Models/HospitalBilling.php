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
        'payment_method', 'receipt_issued_at',
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
        $query = static::query();

        if ($restaurantId !== null) {
            $query->where('restaurant_id', $restaurantId);
        }

        $billings = $query->get(['status', 'total_amount']);

        $sum = function (array $statuses) use ($billings): float {
            return (float) $billings
                ->whereIn('status', $statuses)
                ->sum('total_amount');
        };

        $gross = $sum(['draft', 'posted', 'paid', 'void']);
        $posted = $sum(['posted']);
        $paid = $sum(['paid']);
        $outstanding = $posted;
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
    public function postedBy() { return $this->belongsTo(User::class, 'posted_by'); }
    public function paidBy() { return $this->belongsTo(User::class, 'paid_by'); }

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
