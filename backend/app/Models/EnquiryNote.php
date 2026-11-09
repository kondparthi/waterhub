<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EnquiryNote extends Model
{
    protected $fillable = ['enquiry_id', 'user_id', 'type', 'note'];

    public function user() { return $this->belongsTo(User::class); }
}
