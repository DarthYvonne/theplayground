<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;

class Playlist extends Model
{
    protected $fillable = ['name', 'description', 'image_path'];

    public function mediaItems(): BelongsToMany { return $this->belongsToMany(MediaItem::class); }

    public function imageUrl(): ?string
    {
        return $this->image_path ? Storage::disk('media')->url($this->image_path) : null;
    }

    /** JSON shape for the media picker and shared playlist players. */
    public function toPayload(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'image_url' => $this->imageUrl(),
            'count' => $this->mediaItems->count(),
            'tracks' => $this->mediaItems->map(fn ($mi) => [
                'id' => $mi->id,
                'type' => $mi->type,
                'title' => $mi->title,
                'url' => $mi->url(),
                'thumbnail_url' => $mi->thumbnailUrl(),
            ])->values()->all(),
        ];
    }
}
