<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Message extends Model
{
    protected $fillable = [
        'channel_type',
        'course_id',
        'user_id',
        'body',
        'image_path',
        'video_path',
        'original_video_path',
        'video_processing_status',
        'media_item_id',
        'playlist_id',
    ];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function course(): BelongsTo { return $this->belongsTo(Course::class); }
    public function mediaItem(): BelongsTo { return $this->belongsTo(MediaItem::class); }
    public function playlist(): BelongsTo { return $this->belongsTo(Playlist::class); }

    public function imageUrl(): ?string
    {
        return $this->image_path ? Storage::disk('feed_images')->url($this->image_path) : null;
    }

    public function videoUrl(): ?string
    {
        return $this->video_path ? Storage::disk('feed_videos')->url($this->video_path) : null;
    }
}
