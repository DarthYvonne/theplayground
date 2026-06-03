<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class MediaItem extends Model
{
    public const TYPES = ['video', 'audio', 'image'];

    protected $fillable = [
        'type',
        'title',
        'description',
        'file_path',
        'video_path',
        'original_video_path',
        'video_thumbnail_path',
        'video_processing_status',
        'user_id',
    ];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    /** URL of the playable/displayable file. */
    public function url(): ?string
    {
        $path = $this->type === 'video' ? $this->video_path : $this->file_path;
        return $path ? Storage::disk('media')->url($path) : null;
    }

    public function thumbnailUrl(): ?string
    {
        return $this->video_thumbnail_path
            ? Storage::disk('media')->url($this->video_thumbnail_path)
            : null;
    }

    /** True while a video upload is still being transcoded. */
    public function isProcessing(): bool
    {
        return $this->type === 'video'
            && in_array($this->video_processing_status, ['pending', 'processing'], true);
    }

    public function hasFailed(): bool
    {
        return $this->type === 'video' && $this->video_processing_status === 'failed';
    }

    /** Decide the media type from an upload's content, falling back to extension. */
    public static function detectUploadType(\Illuminate\Http\UploadedFile $file): ?string
    {
        $mime = strtolower((string) $file->getMimeType());
        if (str_starts_with($mime, 'video/')) return 'video';
        if (str_starts_with($mime, 'audio/')) return 'audio';
        if (str_starts_with($mime, 'image/')) return 'image';

        $ext = strtolower($file->getClientOriginalExtension());
        if (in_array($ext, ['mp4', 'mov', 'avi', 'webm', 'm4v', 'mkv'], true)) return 'video';
        if (in_array($ext, ['mp3', 'wav', 'm4a', 'ogg', 'aac', 'flac'], true)) return 'audio';
        if (in_array($ext, ['jpeg', 'jpg', 'png', 'gif', 'webp'], true)) return 'image';

        return null;
    }

    /** Validation rules for an upload of the given (already detected) type. */
    public static function uploadRules(string $type): array
    {
        return match ($type) {
            'video' => ['file', 'mimes:mp4,mov,avi,webm,m4v,mkv', 'max:512000'],
            'audio' => ['file', 'mimes:mp3,wav,m4a,ogg,aac,flac', 'max:51200'],
            'image' => ['file', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:8192'],
        };
    }

    /** JSON shape used by the feed and the media picker. */
    public function toPayload(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->title,
            'description' => $this->description,
            'url' => $this->url(),
            'thumbnail_url' => $this->thumbnailUrl(),
            'processing' => $this->isProcessing(),
        ];
    }

    /** Delete every stored file backing this item. */
    public function deleteFiles(): void
    {
        $disk = Storage::disk('media');
        foreach (['file_path', 'video_path', 'original_video_path', 'video_thumbnail_path'] as $col) {
            if ($this->{$col}) $disk->delete($this->{$col});
        }
    }
}
