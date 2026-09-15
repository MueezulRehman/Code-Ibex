<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;

class Bed extends Model
{
    use BelongsToRestaurant;

    protected $fillable = ['restaurant_id', 'ward_id', 'location_id', 'name', 'code', 'status'];

    public function ward() { return $this->belongsTo(Ward::class); }
    public function location() { return $this->belongsTo(WardLocation::class, 'location_id'); }
    public function admissions() { return $this->hasMany(HospitalAdmission::class, 'bed_id'); }
}
