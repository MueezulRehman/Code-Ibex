<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;

class HospitalMedicationOrder extends Model
{
    use BelongsToRestaurant;

    protected $fillable = [
        'restaurant_id', 'patient_id', 'hospital_admission_id', 'medicine_id',
        'ordered_by', 'order_number', 'dose', 'route', 'frequency',
        'scheduled_start_at', 'scheduled_end_at', 'status', 'ordered_at', 'notes',
    ];

    protected $casts = [
        'scheduled_start_at' => 'datetime',
        'scheduled_end_at' => 'datetime',
        'ordered_at' => 'datetime',
    ];

    public function patient() { return $this->belongsTo(Patient::class); }
    public function admission() { return $this->belongsTo(HospitalAdmission::class, 'hospital_admission_id'); }
    public function medicine() { return $this->belongsTo(Medicine::class); }
    public function orderedBy() { return $this->belongsTo(User::class, 'ordered_by'); }
    public function administrations() { return $this->hasMany(HospitalMedicationAdministration::class); }
}
