<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;

class Doctor extends Model
{
    use BelongsToRestaurant;

    protected $fillable = [
        'restaurant_id',
        'user_id',
        'department_id',
        'name',
        'specialty',
        'phone',
        'email',
        'address',
        'status',
    ];

    public function user() { return $this->belongsTo(User::class); }
    public function department() { return $this->belongsTo(Department::class); }
}
