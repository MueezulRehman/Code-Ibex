<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;

class DischargeSummaryEvent extends Model
{
    use BelongsToRestaurant;

    protected $fillable = [
        'restaurant_id',
        'discharge_summary_id',
        'action',
        'previous_snapshot',
        'new_snapshot',
        'changed_by',
        'changed_at',
        'correction_reason',
    ];

    protected $casts = [
        'previous_snapshot' => 'array',
        'new_snapshot' => 'array',
        'changed_at' => 'datetime',
    ];

    public function summary() { return $this->belongsTo(DischargeSummary::class, 'discharge_summary_id'); }
    public function changedBy() { return $this->belongsTo(User::class, 'changed_by'); }
}
