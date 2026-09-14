<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;

class DischargeSummary extends Model
{
    use BelongsToRestaurant;

    protected $fillable = [
        'restaurant_id',
        'hospital_admission_id',
        'patient_id',
        'discharge_diagnosis',
        'discharge_medications',
        'follow_up_instructions',
        'notes',
        'discharged_by',
        'discharged_at',
    ];

    protected $casts = [
        'discharged_at' => 'datetime',
    ];

    public function admission() { return $this->belongsTo(HospitalAdmission::class, 'hospital_admission_id'); }
    public function patient() { return $this->belongsTo(Patient::class); }
    public function dischargedBy() { return $this->belongsTo(User::class, 'discharged_by'); }
}
