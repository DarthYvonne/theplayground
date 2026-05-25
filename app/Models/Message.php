<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Message extends Model
{
    protected $fillable = ['channel_type','course_id','user_id','body','image_path'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function course(): BelongsTo { return $this->belongsTo(Course::class); }

    public function imageUrl(): ?string
    {
        return $this->image_path ? Storage::disk('feed_images')->url($this->image_path) : null;
    }
}
