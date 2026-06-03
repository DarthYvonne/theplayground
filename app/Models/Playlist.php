<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Playlist extends Model
{
    protected $fillable = ['name'];

    public function mediaItems(): BelongsToMany { return $this->belongsToMany(MediaItem::class); }
}
