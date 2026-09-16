<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;

class HospitalClinicalNote extends Model
{
    use BelongsToRestaurant;

    protected $fillable = [
        'restaurant_id', 'hospital_admission_id', 'author_id', 'note_type',
        'content', 'recorded_at', 'status', 'voided_at', 'voided_by', 'void_reason',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
        'voided_at' => 'datetime',
    ];

    public function admission() { return $this->belongsTo(HospitalAdmission::class, 'hospital_admission_id'); }
    public function author() { return $this->belongsTo(User::class, 'author_id'); }
    public function voidedBy() { return $this->belongsTo(User::class, 'voided_by'); }
    public function events() { return $this->hasMany(HospitalClinicalNoteEvent::class, 'hospital_clinical_note_id'); }
}
