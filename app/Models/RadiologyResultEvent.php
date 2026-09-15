<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;

class RadiologyResultEvent extends Model
{
    use BelongsToRestaurant;

    protected $fillable = [
        'restaurant_id',
        'radiology_result_id',
        'action',
        'previous_values',
        'new_values',
        'changed_by',
        'changed_at',
        'notes',
    ];

    protected $casts = [
        'previous_values' => 'array',
        'new_values' => 'array',
        'changed_at' => 'datetime',
    ];

    public function result() { return $this->belongsTo(RadiologyResult::class, 'radiology_result_id'); }
    public function changedBy() { return $this->belongsTo(User::class, 'changed_by'); }
}
