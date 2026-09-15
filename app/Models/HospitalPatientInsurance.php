<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;

class HospitalPatientInsurance extends Model
{
    use BelongsToRestaurant;

    protected $fillable = [
        'restaurant_id', 'patient_id', 'provider_id', 'payer', 'policy_number',
        'member_number', 'coverage_starts', 'coverage_ends', 'status', 'notes',
    ];

    protected $casts = [
        'coverage_starts' => 'date',
        'coverage_ends' => 'date',
    ];

    public function patient() { return $this->belongsTo(Patient::class); }
    public function provider() { return $this->belongsTo(InsuranceProvider::class, 'provider_id'); }
    public function admissions() { return $this->hasMany(HospitalAdmission::class, 'hospital_patient_insurance_id'); }
    public function billings() { return $this->hasMany(HospitalBilling::class, 'hospital_patient_insurance_id'); }

    public function label(): string
    {
        $number = $this->member_number ?: $this->policy_number ?: 'No policy number';
        return "{$this->payer} · {$number}";
    }
}
