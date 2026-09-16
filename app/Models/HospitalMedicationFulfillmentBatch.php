<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;

class HospitalMedicationFulfillmentBatch extends Model
{
    use BelongsToRestaurant;

    protected $fillable = [
        'restaurant_id', 'hospital_medication_fulfillment_id',
        'medicine_batch_id', 'quantity_dispensed',
    ];

    protected $casts = [
        'quantity_dispensed' => 'decimal:3',
    ];

    public function fulfillment()
    {
        return $this->belongsTo(HospitalMedicationFulfillment::class, 'hospital_medication_fulfillment_id');
    }

    public function batch()
    {
        return $this->belongsTo(MedicineBatch::class, 'medicine_batch_id');
    }
}
