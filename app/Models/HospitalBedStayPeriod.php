<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;

class HospitalBedStayPeriod extends Model
{
    use BelongsToRestaurant;

    protected $fillable = [
        'restaurant_id', 'hospital_admission_id', 'bed_id', 'ward_id',
        'started_at', 'ended_at', 'daily_rate', 'hospital_billing_id',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'daily_rate' => 'decimal:2',
    ];

    public function admission() { return $this->belongsTo(HospitalAdmission::class, 'hospital_admission_id'); }
    public function bed() { return $this->belongsTo(Bed::class); }
    public function ward() { return $this->belongsTo(Ward::class); }
    public function billing() { return $this->belongsTo(HospitalBilling::class, 'hospital_billing_id'); }
}
