<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;

class HospitalTransfer extends Model
{
    use BelongsToRestaurant;

    protected $fillable = [
        'restaurant_id', 'hospital_admission_id', 'from_department_id',
        'to_department_id', 'from_bed_id', 'to_bed_id', 'requested_by',
        'transferred_at', 'reason', 'notes',
    ];

    protected $casts = ['transferred_at' => 'datetime'];

    public function admission() { return $this->belongsTo(HospitalAdmission::class, 'hospital_admission_id'); }
    public function fromDepartment() { return $this->belongsTo(Department::class, 'from_department_id'); }
    public function toDepartment() { return $this->belongsTo(Department::class, 'to_department_id'); }
    public function fromBed() { return $this->belongsTo(Bed::class, 'from_bed_id'); }
    public function toBed() { return $this->belongsTo(Bed::class, 'to_bed_id'); }
    public function requestedBy() { return $this->belongsTo(User::class, 'requested_by'); }
}
