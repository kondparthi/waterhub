<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WaterType extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'is_active', 'sort_order'];
    protected $casts = ['is_active' => 'boolean'];
}
