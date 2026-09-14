<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;

class Patient extends Model
{
    use BelongsToRestaurant;

    protected $fillable = [
        'restaurant_id',
        'patient_number',
        'name',
        'cnic',
        'phone',
        'notification_consent',
        'email',
        'date_of_birth',
        'gender',
        'is_dependent',
        'guardian_name',
        'guardian_cnic',
        'guardian_phone',
        'relationship',
        'address',
        'emergency_contact_name',
        'emergency_contact_phone',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'is_dependent' => 'boolean',
        'notification_consent' => 'boolean',
    ];

    public function allergies()
    {
        return $this->hasMany(PatientAllergy::class);
    }

    public function hospitalAdmissions()
    {
        return $this->hasMany(HospitalAdmission::class, 'patient_id')->latest('admitted_at');
    }
    public function hospitalBillings() { return $this->hasMany(HospitalBilling::class); }

    public function labOrders() { return $this->hasMany(LabOrder::class); }
    public function labResults() { return $this->hasMany(LabResult::class); }
    public function radiologyOrders() { return $this->hasMany(RadiologyOrder::class); }
    public function radiologyResults() { return $this->hasMany(RadiologyResult::class); }
    public function surgeries() { return $this->hasMany(Surgery::class); }
}
