<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;

class Ward extends Model
{
    use BelongsToRestaurant;

    protected $fillable = ['restaurant_id', 'name', 'code', 'description', 'is_active', 'room_category', 'daily_rate'];
    protected $casts = ['is_active' => 'boolean', 'daily_rate' => 'decimal:2'];

    public function beds() { return $this->hasMany(Bed::class); }
    public function locations() { return $this->hasMany(WardLocation::class); }
}
