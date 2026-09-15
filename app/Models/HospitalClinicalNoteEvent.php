<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;

class HospitalClinicalNoteEvent extends Model
{
    use BelongsToRestaurant;

    protected $fillable = [
        'restaurant_id', 'hospital_clinical_note_id', 'action', 'previous_content',
        'new_content', 'previous_recorded_at', 'new_recorded_at', 'changed_by',
        'changed_at', 'notes',
    ];

    protected $casts = [
        'previous_recorded_at' => 'datetime',
        'new_recorded_at' => 'datetime',
        'changed_at' => 'datetime',
    ];

    public function note() { return $this->belongsTo(HospitalClinicalNote::class, 'hospital_clinical_note_id'); }
    public function changedBy() { return $this->belongsTo(User::class, 'changed_by'); }
}
