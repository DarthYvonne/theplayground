<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Course extends Model
{
    use HasFactory;

    protected $fillable = [
        'title','description','image_path','price_cents',
        'max_participants','is_active','free_enrollment','stripe_product_id','stripe_price_id',
        'start_time','end_time','weekdays',
        'video_path','original_video_path','video_processing_status','video_thumbnail_path',
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

    private const ISO_DAYS = ['mon' => 1, 'tue' => 2, 'wed' => 3, 'thu' => 4, 'fri' => 5, 'sat' => 6, 'sun' => 7];

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

    public function runsOn(Carbon $day): bool
    {
        $days = $this->weekdaysList();
        if (! $days) return false;
        return in_array($day->isoWeekday(), array_map(fn ($d) => self::ISO_DAYS[$d], $days), true);
    }

    /**
     * The next date this course actually runs, skipping the given YYYY-MM-DD
     * dates (cancellations). A session counts as "next" until it ends, so a
     * course does not jump to next week the minute it starts.
     *
     * @param  array<string> $skipDates
     */
    public function nextOccurrence(?Carbon $from = null, array $skipDates = []): ?Carbon
    {
        if (! $this->weekdaysList()) return null;

        $from = $from ? $from->copy() : Carbon::now();
        $skip = array_flip($skipDates);

        // Two weeks clears a full cycle plus any run of cancelled sessions.
        for ($i = 0; $i <= 14; $i++) {
            $day = $from->copy()->addDays($i)->startOfDay();
            if (! $this->runsOn($day)) continue;
            if (isset($skip[$day->toDateString()])) continue;
            if ($this->occurrenceEnd($day)->lt($from)) continue;
            return $this->occurrenceStart($day);
        }

        return null;
    }

    /** "I dag kl. 17:00" / "I morgen kl. 17:00" / "Onsdag kl. 17:00" / "12.08. kl. 17:00". */
    public function occurrenceLabel(Carbon $occurrence, ?Carbon $now = null): string
    {
        $now = $now ? $now->copy() : Carbon::now();
        $time = $this->start_time ? ' kl. ' . substr((string) $this->start_time, 0, 5) : '';
        $days = (int) $now->copy()->startOfDay()->diffInDays($occurrence->copy()->startOfDay(), false);

        if ($days === 0) return 'I dag' . $time;
        if ($days === 1) return 'I morgen' . $time;
        if ($days < 7) return self::WEEKDAYS[array_search($occurrence->isoWeekday(), self::ISO_DAYS, true)] . $time;
        return $occurrence->format('d.m.') . $time;
    }

    private function occurrenceStart(Carbon $day): Carbon
    {
        [$h, $m] = $this->timeParts($this->start_time, '00:00');
        return $day->copy()->setTime($h, $m);
    }

    private function occurrenceEnd(Carbon $day): Carbon
    {
        [$h, $m] = $this->timeParts($this->end_time ?: $this->start_time, '23:59');
        return $day->copy()->setTime($h, $m);
    }

    /** @return array{0:int,1:int} */
    private function timeParts(?string $time, string $fallback): array
    {
        $parts = explode(':', $time ? substr((string) $time, 0, 5) : $fallback);
        return [(int) ($parts[0] ?? 0), (int) ($parts[1] ?? 0)];
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

    public function trainers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'course_trainer')->withTimestamps()->orderBy('users.name');
    }

    public function hasTrainer(?User $user): bool
    {
        if (!$user) return false;
        return $this->trainers()->whereKey($user->id)->exists();
    }

    public function primaryTrainer(): ?User
    {
        return $this->trainers->first();
    }

    public function trainerNames(): string
    {
        $names = $this->trainers->pluck('name')->all();
        if (!$names) return '';
        if (count($names) === 1) return $names[0];
        $last = array_pop($names);
        return implode(', ', $names) . ' og ' . $last;
    }

    public function enrollments(): HasMany { return $this->hasMany(Enrollment::class); }
    public function activeEnrollments(): HasMany { return $this->enrollments()->where('status','active'); }
    public function messages(): HasMany { return $this->hasMany(Message::class)->where('channel_type','course'); }
    public function cancellations(): HasMany { return $this->hasMany(CourseCancellation::class); }

    public function imageUrl(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }

    public function videoUrl(): ?string
    {
        return $this->video_path ? Storage::disk('course_videos')->url($this->video_path) : null;
    }

    public function videoThumbnailUrl(): ?string
    {
        return $this->video_thumbnail_path ? Storage::disk('course_videos')->url($this->video_thumbnail_path) : null;
    }

    public function hasVideo(): bool
    {
        return !empty($this->video_path);
    }

    /**
     * URL of the still image shown on listing tiles.
     * Prefers the video thumbnail (auto-generated from an uploaded video) so that
     * "Hold med video" still look right in catalogs that can't autoplay a player.
     */
    public function heroImageUrl(): ?string
    {
        return $this->videoThumbnailUrl() ?? $this->imageUrl();
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
