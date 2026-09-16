<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;

class HospitalMedicationFulfillment extends Model
{
    use BelongsToRestaurant;

    protected $fillable = [
        'restaurant_id', 'hospital_medication_order_id', 'medicine_id',
        'medicine_batch_id', 'fulfilled_by', 'quantity_requested',
        'quantity_dispensed', 'status', 'dispensed_at', 'notes',
        'hospital_billing_id',
    ];

    protected $casts = [
        'quantity_requested' => 'decimal:3',
        'quantity_dispensed' => 'decimal:3',
        'dispensed_at' => 'datetime',
    ];

    public function order() { return $this->belongsTo(HospitalMedicationOrder::class, 'hospital_medication_order_id'); }
    public function medicine() { return $this->belongsTo(Medicine::class); }
    public function batch() { return $this->belongsTo(MedicineBatch::class, 'medicine_batch_id'); }
    public function fulfilledBy() { return $this->belongsTo(User::class, 'fulfilled_by'); }
    public function billing() { return $this->belongsTo(HospitalBilling::class, 'hospital_billing_id'); }
    public function batches() { return $this->hasMany(HospitalMedicationFulfillmentBatch::class, 'hospital_medication_fulfillment_id'); }
}
