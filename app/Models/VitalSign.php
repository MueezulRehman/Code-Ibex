<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;

class VitalSign extends Model
{
    use BelongsToRestaurant;

    protected $fillable = ['restaurant_id', 'patient_id', 'hospital_admission_id', 'recorded_by', 'recorded_at', 'temperature', 'blood_pressure', 'pulse', 'respiratory_rate', 'oxygen_saturation', 'weight', 'pain_score', 'notes'];
    protected $casts = ['recorded_at' => 'datetime', 'temperature' => 'decimal:1', 'oxygen_saturation' => 'decimal:1', 'weight' => 'decimal:2'];

    public function patient() { return $this->belongsTo(Patient::class); }
    public function admission() { return $this->belongsTo(HospitalAdmission::class, 'hospital_admission_id'); }
    public function recorder() { return $this->belongsTo(User::class, 'recorded_by'); }
}
