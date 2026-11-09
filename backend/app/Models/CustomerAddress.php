<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerAddress extends Model
{
    protected $fillable = ['user_id', 'label', 'address', 'area', 'landmark', 'latitude', 'longitude', 'is_default'];
    protected $casts = ['latitude' => 'float', 'longitude' => 'float', 'is_default' => 'boolean'];

    public function user() { return $this->belongsTo(User::class); }
}
