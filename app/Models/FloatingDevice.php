<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FloatingDevice extends Model
{
    protected $fillable = ['name','type','sort_order','is_active'];

    public const TYPES = ['single', 'double'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function bookings(): HasMany { return $this->hasMany(FloatingBooking::class, 'device_id'); }

    public function typeLabel(): string
    {
        return $this->type === 'double' ? 'Dobbelt' : 'Enkelt';
    }
}
