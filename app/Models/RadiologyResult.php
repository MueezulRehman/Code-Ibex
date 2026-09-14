<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;

class RadiologyResult extends Model
{
    use BelongsToRestaurant;

    protected $fillable = [
        'restaurant_id', 'radiology_order_id', 'patient_id', 'hospital_admission_id',
        'entered_by', 'verified_by', 'result_value', 'status', 'resulted_at',
        'verified_at', 'notes',
    ];

    protected $casts = ['resulted_at' => 'datetime', 'verified_at' => 'datetime'];

    public function order() { return $this->belongsTo(RadiologyOrder::class, 'radiology_order_id'); }
    public function patient() { return $this->belongsTo(Patient::class); }
    public function admission() { return $this->belongsTo(HospitalAdmission::class, 'hospital_admission_id'); }
    public function enteredBy() { return $this->belongsTo(User::class, 'entered_by'); }
    public function verifiedBy() { return $this->belongsTo(User::class, 'verified_by'); }
}
