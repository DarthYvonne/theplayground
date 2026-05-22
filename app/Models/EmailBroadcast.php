<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailBroadcast extends Model
{
    protected $fillable = ['course_id','sender_id','subject','body','recipient_count','sent_at'];
    protected function casts(): array { return ['sent_at' => 'datetime']; }
    public function course(): BelongsTo { return $this->belongsTo(Course::class); }
    public function sender(): BelongsTo { return $this->belongsTo(User::class, 'sender_id'); }
}
