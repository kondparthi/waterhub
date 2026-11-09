<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TankerLocation extends Model
{
    public $timestamps = false;
    protected $fillable = ['assignment_id', 'latitude', 'longitude', 'accuracy_m', 'speed', 'heading', 'recorded_at', 'received_at'];
    protected $casts = ['latitude' => 'float', 'longitude' => 'float', 'accuracy_m' => 'float', 'speed' => 'float', 'heading' => 'float', 'recorded_at' => 'datetime', 'received_at' => 'datetime'];
}
