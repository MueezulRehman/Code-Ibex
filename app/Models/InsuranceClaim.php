<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;

class InsuranceClaim extends Model
{
    use BelongsToRestaurant;
    protected $fillable = ['restaurant_id', 'provider_id', 'customer_id', 'hospital_patient_insurance_id', 'policy_number', 'claim_number', 'claimed_amount', 'approved_amount', 'status', 'notes'];
    protected $casts = ['claimed_amount' => 'decimal:2', 'approved_amount' => 'decimal:2'];
    public function provider()
    {
        return $this->belongsTo(InsuranceProvider::class, 'provider_id');
    }
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
    public function hospitalPatientInsurance()
    {
        return $this->belongsTo(HospitalPatientInsurance::class, 'hospital_patient_insurance_id');
    }
}
