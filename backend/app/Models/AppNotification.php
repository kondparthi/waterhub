<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppNotification extends Model
{
    protected $table = 'notifications';
    protected $fillable = ['user_id', 'title', 'message', 'type', 'data_json', 'read_at'];
    protected $casts = ['data_json' => 'array', 'read_at' => 'datetime'];
}
