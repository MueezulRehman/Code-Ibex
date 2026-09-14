<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;

class NursingAssignmentEvent extends Model
{
    use BelongsToRestaurant;

    protected $fillable = [
        'restaurant_id',
        'nursing_assignment_id',
        'action',
        'previous_nurse_id',
        'new_nurse_id',
        'changed_by',
        'changed_at',
        'notes',
    ];

    protected $casts = ['changed_at' => 'datetime'];

    public function assignment() { return $this->belongsTo(NursingAssignment::class, 'nursing_assignment_id'); }
    public function previousNurse() { return $this->belongsTo(User::class, 'previous_nurse_id'); }
    public function newNurse() { return $this->belongsTo(User::class, 'new_nurse_id'); }
    public function changedBy() { return $this->belongsTo(User::class, 'changed_by'); }
}
