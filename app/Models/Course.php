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
        'max_participants','is_active','free_enrollment','stripe_product_id','stripe_price_id',
        'start_time','end_time','weekdays',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'free_enrollment' => 'boolean',
            'price_cents' => 'integer',
            'max_participants' => 'integer',
        ];
    }

    public const WEEKDAYS = [
        'mon' => 'Mandag', 'tue' => 'Tirsdag', 'wed' => 'Onsdag',
        'thu' => 'Torsdag', 'fri' => 'Fredag', 'sat' => 'Lørdag', 'sun' => 'Søndag',
    ];

    /** @return array<string> */
    public function weekdaysList(): array
    {
        if (!$this->weekdays) return [];
        return array_values(array_filter(explode(',', $this->weekdays), fn ($d) => isset(self::WEEKDAYS[$d])));
    }

    public function scheduleLabel(): ?string
    {
        $days = $this->weekdaysList();
        $time = $this->timeRange();
        if (!$days && !$time) return null;
        $dayPart = $this->daysLabel($days);
        return trim(trim($dayPart) . ($time ? ' · ' . $time : ''));
    }

    public function timeRange(): ?string
    {
        if (!$this->start_time && !$this->end_time) return null;
        $fmt = fn ($t) => $t ? substr((string) $t, 0, 5) : '';
        if ($this->start_time && $this->end_time) return $fmt($this->start_time) . '–' . $fmt($this->end_time);
        return $fmt($this->start_time ?: $this->end_time);
    }

    private function daysLabel(array $days): string
    {
        if (!$days) return '';
        if (count($days) === 7) return 'Hver dag';
        // Weekdays = mon..fri
        $weekdays = ['mon','tue','wed','thu','fri'];
        $weekend = ['sat','sun'];
        sort($days);
        if ($days === $weekdays) return 'Hverdage';
        if ($days === $weekend) return 'Weekend';
        $names = array_map(fn ($d) => self::WEEKDAYS[$d], $days);
        if (count($names) === 1) return $names[0];
        $last = array_pop($names);
        return implode(', ', $names) . ' og ' . $last;
    }

    public function trainer(): BelongsTo { return $this->belongsTo(User::class, 'trainer_id'); }
    public function enrollments(): HasMany { return $this->hasMany(Enrollment::class); }
    public function activeEnrollments(): HasMany { return $this->enrollments()->where('status','active'); }
    public function messages(): HasMany { return $this->hasMany(Message::class)->where('channel_type','course'); }
    public function cancellations(): HasMany { return $this->hasMany(CourseCancellation::class); }

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
