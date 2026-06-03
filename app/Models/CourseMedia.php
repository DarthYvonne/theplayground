<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class CourseMedia extends Model
{
    protected $table = 'course_media';

    protected $fillable = [
        'course_id',
        'user_id',
        'media_item_id',
        'playlist_id',
        'type',
        'file_path',
        'video_path',
        'original_video_path',
        'video_thumbnail_path',
        'video_processing_status',
        'comment',
    ];

    public function course(): BelongsTo { return $this->belongsTo(Course::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function mediaItem(): BelongsTo { return $this->belongsTo(MediaItem::class); }
    public function playlist(): BelongsTo { return $this->belongsTo(Playlist::class); }

    public function url(): ?string
    {
        if ($this->media_item_id) return $this->mediaItem?->url();
        $path = $this->type === 'video' ? $this->video_path : $this->file_path;
        return $path ? Storage::disk('media')->url($path) : null;
    }

    public function thumbnailUrl(): ?string
    {
        if ($this->media_item_id) return $this->mediaItem?->thumbnailUrl();
        return $this->video_thumbnail_path ? Storage::disk('media')->url($this->video_thumbnail_path) : null;
    }

    public function isProcessing(): bool
    {
        if ($this->media_item_id) return (bool) $this->mediaItem?->isProcessing();
        return $this->type === 'video'
            && in_array($this->video_processing_status, ['pending', 'processing'], true);
    }

    public function hasFailed(): bool
    {
        if ($this->media_item_id) return (bool) $this->mediaItem?->hasFailed();
        return $this->type === 'video' && $this->video_processing_status === 'failed';
    }

    /** Delete this entry's own uploaded files — never the linked library item's. */
    public function deleteFiles(): void
    {
        if ($this->media_item_id) return;
        $disk = Storage::disk('media');
        foreach (['file_path', 'video_path', 'original_video_path', 'video_thumbnail_path'] as $col) {
            if ($this->{$col}) $disk->delete($this->{$col});
        }
    }
}
