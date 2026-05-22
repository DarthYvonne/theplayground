<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Course extends Model
{
    use HasFactory;

    protected $fillable = [
        'title','description','trainer_id','image_path','price_cents',
        'max_participants','is_active','stripe_product_id','stripe_price_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'price_cents' => 'integer',
            'max_participants' => 'integer',
        ];
    }

    public function trainer(): BelongsTo { return $this->belongsTo(User::class, 'trainer_id'); }
    public function enrollments(): HasMany { return $this->hasMany(Enrollment::class); }
    public function activeEnrollments(): HasMany { return $this->enrollments()->where('status','active'); }
    public function messages(): HasMany { return $this->hasMany(Message::class)->where('channel_type','course'); }
    public function emailBroadcasts(): HasMany { return $this->hasMany(EmailBroadcast::class); }

    public function imageUrl(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }

    public function price(): string
    {
        $amt = number_format($this->price_cents / 100, $this->price_cents % 100 === 0 ? 0 : 2, ',', '.');
        return $amt . ' kr/md';
    }

    public function activeCount(): int { return $this->activeEnrollments()->count(); }
    public function isFull(): bool { return $this->activeCount() >= $this->max_participants; }
    public function slotsLeft(): int { return max(0, $this->max_participants - $this->activeCount()); }
}
