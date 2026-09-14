<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;

class LabOrder extends Model
{
    use BelongsToRestaurant;

    protected $fillable = [
        'restaurant_id', 'patient_id', 'hospital_admission_id', 'ordered_by',
        'order_number', 'test_name', 'priority', 'status', 'ordered_at', 'clinical_notes',
    ];

    protected $casts = ['ordered_at' => 'datetime'];

    public function patient() { return $this->belongsTo(Patient::class); }
    public function admission() { return $this->belongsTo(HospitalAdmission::class, 'hospital_admission_id'); }
    public function orderedBy() { return $this->belongsTo(User::class, 'ordered_by'); }
    public function result() { return $this->hasOne(LabResult::class); }
}
