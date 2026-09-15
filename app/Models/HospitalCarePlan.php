<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;

class HospitalCarePlan extends Model
{
    use BelongsToRestaurant;

    protected $fillable = [
        'restaurant_id', 'hospital_admission_id', 'patient_id', 'problem_diagnosis',
        'goal', 'interventions', 'responsible_role', 'responsible_user_id',
        'status', 'opened_at', 'review_due_at', 'resolved_at', 'notes',
        'nursing_progress', 'nursing_status', 'nursing_progress_at',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'review_due_at' => 'datetime',
        'resolved_at' => 'datetime',
        'nursing_progress_at' => 'datetime',
    ];

    public function admission() { return $this->belongsTo(HospitalAdmission::class, 'hospital_admission_id'); }
    public function patient() { return $this->belongsTo(Patient::class); }
    public function responsibleUser() { return $this->belongsTo(User::class, 'responsible_user_id'); }
    public function events() { return $this->hasMany(HospitalCarePlanEvent::class, 'hospital_care_plan_id')->latest('changed_at'); }
}
