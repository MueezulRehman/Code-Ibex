<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;

class HospitalStaffRoster extends Model
{
    use BelongsToRestaurant;

    protected $table = 'hospital_staff_rosters';

    protected $fillable = [
        'restaurant_id',
        'staff_user_id',
        'shift_date',
        'starts_at',
        'ends_at',
        'shift_type',
        'status',
        'department_id',
        'ward_id',
        'notes',
    ];

    protected $casts = [
        'shift_date' => 'date',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function staffUser()
    {
        return $this->belongsTo(User::class, 'staff_user_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function ward()
    {
        return $this->belongsTo(Ward::class);
    }
}
