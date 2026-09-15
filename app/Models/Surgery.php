<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;

class Surgery extends Model
{
    use BelongsToRestaurant;

    protected $fillable = [
        'restaurant_id', 'patient_id', 'hospital_admission_id', 'operating_room_id',
        'surgeon_id', 'scheduled_by', 'surgery_number', 'procedure_name', 'status',
        'anaesthetist_id', 'assistant_surgeon_id', 'surgical_team_details',
        'consent_status', 'preop_status', 'preop_checklist', 'postoperative_notes',
        'completed_at', 'starts_at', 'ends_at', 'notes',
    ];

    protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'completed_at' => 'datetime', 'preop_checklist' => 'array'];

    public function patient() { return $this->belongsTo(Patient::class); }
    public function admission() { return $this->belongsTo(HospitalAdmission::class, 'hospital_admission_id'); }
    public function operatingRoom() { return $this->belongsTo(OperatingRoom::class); }
    public function surgeon() { return $this->belongsTo(User::class, 'surgeon_id'); }
    public function anaesthetist() { return $this->belongsTo(User::class, 'anaesthetist_id'); }
    public function assistantSurgeon() { return $this->belongsTo(User::class, 'assistant_surgeon_id'); }
    public function scheduledBy() { return $this->belongsTo(User::class, 'scheduled_by'); }
}
