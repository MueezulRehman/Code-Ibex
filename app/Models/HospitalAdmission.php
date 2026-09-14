<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;

class HospitalAdmission extends Model
{
    use BelongsToRestaurant;

    protected $fillable = [
        'restaurant_id', 'admission_number', 'patient_id', 'doctor_id',
        'department_id', 'status', 'admitted_at', 'discharged_at', 'notes',
        'bed_id',
    ];

    protected $casts = [
        'admitted_at' => 'datetime',
        'discharged_at' => 'datetime',
    ];

    public function patient() { return $this->belongsTo(Patient::class); }
    public function doctor() { return $this->belongsTo(Doctor::class); }
    public function department() { return $this->belongsTo(Department::class); }
    public function bed() { return $this->belongsTo(Bed::class); }
    public function nursingAssignments() { return $this->hasMany(NursingAssignment::class, 'hospital_admission_id'); }
}
