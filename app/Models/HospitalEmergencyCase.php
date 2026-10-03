<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;

class HospitalEmergencyCase extends Model
{
    use BelongsToRestaurant;

    protected $fillable = [
        'restaurant_id', 'patient_id', 'doctor_id', 'nurse_id', 'department_id',
        'hospital_admission_id', 'case_number', 'priority', 'status', 'destination',
        'arrived_at', 'triaged_at', 'routed_at', 'chief_complaint', 'triage_notes',
        'emergency_charge_amount', 'routing_notes',
    ];

    protected $casts = [
        'arrived_at' => 'datetime',
        'triaged_at' => 'datetime',
        'routed_at' => 'datetime',
        'emergency_charge_amount' => 'decimal:2',
    ];

    public function patient() { return $this->belongsTo(Patient::class); }
    public function doctor() { return $this->belongsTo(Doctor::class); }
    public function nurse() { return $this->belongsTo(User::class, 'nurse_id'); }
    public function department() { return $this->belongsTo(Department::class); }
    public function admission() { return $this->belongsTo(HospitalAdmission::class, 'hospital_admission_id'); }
    public function vitals()
    {
        return $this->hasManyThrough(
            VitalSign::class,
            HospitalAdmission::class,
            'id',
            'hospital_admission_id',
            'hospital_admission_id',
            'id'
        );
    }
}
