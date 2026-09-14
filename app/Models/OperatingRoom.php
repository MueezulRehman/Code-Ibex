<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;

class OperatingRoom extends Model
{
    use BelongsToRestaurant;

    protected $fillable = ['restaurant_id', 'name', 'code', 'status', 'notes'];

    public function surgeries() { return $this->hasMany(Surgery::class); }
}
