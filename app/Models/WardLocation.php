<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;

class WardLocation extends Model
{
    use BelongsToRestaurant;

    protected $fillable = [
        'restaurant_id',
        'ward_id',
        'type',
        'name',
        'code',
        'description',
        'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function ward() { return $this->belongsTo(Ward::class); }
    public function beds() { return $this->hasMany(Bed::class, 'location_id'); }
}
