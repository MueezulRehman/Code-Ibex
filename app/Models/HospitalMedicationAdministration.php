<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;

class HospitalMedicationAdministration extends Model
{
    use BelongsToRestaurant;

    protected $fillable = [
        'restaurant_id', 'patient_id', 'hospital_admission_id',
        'hospital_medication_order_id', 'recorded_by', 'administered_by',
        'dose', 'route', 'scheduled_at', 'administered_at', 'status',
        'idempotency_key', 'reason', 'notes', 'recorded_at',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'administered_at' => 'datetime',
        'recorded_at' => 'datetime',
    ];

    public function patient() { return $this->belongsTo(Patient::class); }
    public function admission() { return $this->belongsTo(HospitalAdmission::class, 'hospital_admission_id'); }
    public function order() { return $this->belongsTo(HospitalMedicationOrder::class, 'hospital_medication_order_id'); }
    public function recordedBy() { return $this->belongsTo(User::class, 'recorded_by'); }
    public function administeredBy() { return $this->belongsTo(User::class, 'administered_by'); }
}
