<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;

class HospitalCarePlanEvent extends Model
{
    use BelongsToRestaurant;

    protected $fillable = [
        'restaurant_id', 'hospital_care_plan_id', 'action', 'previous_values',
        'new_values', 'changed_by', 'changed_at', 'notes',
    ];

    protected $casts = [
        'previous_values' => 'array',
        'new_values' => 'array',
        'changed_at' => 'datetime',
    ];

    public function plan() { return $this->belongsTo(HospitalCarePlan::class, 'hospital_care_plan_id'); }
    public function changedBy() { return $this->belongsTo(User::class, 'changed_by'); }
}
