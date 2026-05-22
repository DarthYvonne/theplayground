<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    public $timestamps = false;
    protected $fillable = ['key', 'value', 'updated_at'];

    // Encrypted at rest. Decrypted via accessor below.
    protected $casts = ['value' => 'encrypted', 'updated_at' => 'datetime'];

    public static function get(string $key, ?string $default = null): ?string
    {
        $cache = Cache::remember('settings.all', 60, fn () => self::pluck('value', 'key')->all());
        return $cache[$key] ?? $default;
    }

    public static function put(string $key, ?string $value): void
    {
        self::updateOrCreate(['key' => $key], ['value' => $value, 'updated_at' => now()]);
        Cache::forget('settings.all');
    }

    public static function many(): array
    {
        return Cache::remember('settings.all', 60, fn () => self::pluck('value', 'key')->all());
    }
}
