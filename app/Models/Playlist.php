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
}
