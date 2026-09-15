<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;

class BedTransfer extends Model
{
    use BelongsToRestaurant;

    protected $fillable = [
        'restaurant_id',
        'hospital_admission_id',
        'patient_id',
        'previous_bed_id',
        'new_bed_id',
        'previous_ward_id',
        'new_ward_id',
        'previous_department_id',
        'new_department_id',
        'requested_by',
        'approved_by',
        'transferred_at',
        'reason',
        'notes',
    ];

    protected $casts = ['transferred_at' => 'datetime'];

    public function admission() { return $this->belongsTo(HospitalAdmission::class, 'hospital_admission_id'); }
    public function patient() { return $this->belongsTo(Patient::class, 'patient_id'); }
    public function previousBed() { return $this->belongsTo(Bed::class, 'previous_bed_id'); }
    public function newBed() { return $this->belongsTo(Bed::class, 'new_bed_id'); }
    public function previousWard() { return $this->belongsTo(Ward::class, 'previous_ward_id'); }
    public function newWard() { return $this->belongsTo(Ward::class, 'new_ward_id'); }
    public function previousDepartment() { return $this->belongsTo(Department::class, 'previous_department_id'); }
    public function newDepartment() { return $this->belongsTo(Department::class, 'new_department_id'); }
    public function requestedBy() { return $this->belongsTo(User::class, 'requested_by'); }
    public function approvedBy() { return $this->belongsTo(User::class, 'approved_by'); }
}
