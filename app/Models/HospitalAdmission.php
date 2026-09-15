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
    public function vitalSigns() { return $this->hasMany(VitalSign::class, 'hospital_admission_id'); }
    public function transfers() { return $this->hasMany(HospitalTransfer::class, 'hospital_admission_id')->latest('transferred_at'); }
    public function bedTransfers() { return $this->hasMany(BedTransfer::class, 'hospital_admission_id')->latest('transferred_at'); }
    public function dischargeSummary() { return $this->hasOne(DischargeSummary::class, 'hospital_admission_id'); }
    public function labOrders() { return $this->hasMany(LabOrder::class, 'hospital_admission_id'); }
    public function labResults() { return $this->hasMany(LabResult::class, 'hospital_admission_id'); }
    public function radiologyOrders() { return $this->hasMany(RadiologyOrder::class, 'hospital_admission_id'); }
    public function radiologyResults() { return $this->hasMany(RadiologyResult::class, 'hospital_admission_id'); }
    public function surgeries() { return $this->hasMany(Surgery::class, 'hospital_admission_id'); }
    public function medicationOrders() { return $this->hasMany(HospitalMedicationOrder::class, 'hospital_admission_id'); }
    public function medicationAdministrations() { return $this->hasMany(HospitalMedicationAdministration::class, 'hospital_admission_id'); }
    public function billings() { return $this->hasMany(HospitalBilling::class, 'hospital_admission_id'); }

    public function billingSummary(): array
    {
        $billings = $this->relationLoaded('billings')
            ? $this->billings
            : $this->billings()->get(['status', 'total_amount']);

        $gross = $billings->sum('total_amount');
        $posted = $billings->where('status', 'posted')->sum('total_amount');
        $paid = $billings->where('status', 'paid')->sum('total_amount');
        $outstanding = $posted;
        $draft = $billings->where('status', 'draft')->sum('total_amount');
        $void = $billings->where('status', 'void')->sum('total_amount');

        return [
            'gross' => (float) $gross,
            'posted' => (float) $posted,
            'paid' => (float) $paid,
            'outstanding' => (float) $outstanding,
            'draft' => (float) $draft,
            'void' => (float) $void,
            'total' => (float) $gross,
        ];
    }

    public function hasUnpaidPostedBilling(): bool
    {
        return $this->billings()->where('status', 'posted')->exists();
    }
}
