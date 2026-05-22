<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MessageRead extends Model
{
    public $timestamps = false;
    protected $fillable = ['user_id','course_id','last_read_at'];
    protected function casts(): array { return ['last_read_at' => 'datetime']; }
}
