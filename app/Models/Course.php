<?php

namespace App\Models;

use App\Support\CourseOccurrence;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Course extends Model
{
    use HasFactory;

    public const TYPE_HOLD = 'hold';
    public const TYPE_FAELLES = 'faellestraening';
    public const TYPE_PERSONLIG = 'personlig_traening';

    public const TYPES = [
        self::TYPE_HOLD => 'Hold',
        self::TYPE_FAELLES => 'Fællestræning',
        self::TYPE_PERSONLIG => 'Personlig træning',
    ];

    protected $fillable = [
        'title','type','description','image_path','price_cents',
        'max_participants','is_active','free_enrollment','chat_enabled','stripe_product_id','stripe_price_id',
        'video_path','original_video_path','video_processing_status','video_thumbnail_path',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'free_enrollment' => 'boolean',
            'chat_enabled' => 'boolean',
            'price_cents' => 'integer',
            'max_participants' => 'integer',
        ];
    }

    public const WEEKDAYS = [
        'mon' => 'Mandag', 'tue' => 'Tirsdag', 'wed' => 'Onsdag',
        'thu' => 'Torsdag', 'fri' => 'Fredag', 'sat' => 'Lørdag', 'sun' => 'Søndag',
    ];

    private const ISO_DAYS = ['mon' => 1, 'tue' => 2, 'wed' => 3, 'thu' => 4, 'fri' => 5, 'sat' => 6, 'sun' => 7];

    public function schedules(): HasMany
    {
        return $this->hasMany(CourseSchedule::class);
    }

    public static function weekdayKey(Carbon $day): string
    {
        return array_search($day->isoWeekday(), self::ISO_DAYS, true) ?: 'mon';
    }

    /** Slots in calendar order: Monday first, earliest slot first. */
    public function orderedSchedules(): Collection
    {
        $order = array_flip(array_keys(self::WEEKDAYS));
        return $this->schedules
            ->sortBy(fn (CourseSchedule $s) => sprintf('%d%s', $order[$s->weekday] ?? 9, $s->start_time ?? ''))
            ->values();
    }

    /** @return array<string> */
    public function weekdaysList(): array
    {
        return $this->orderedSchedules()->pluck('weekday')->unique()->values()->all();
    }

    /** @return Collection<int, CourseSchedule> */
    public function schedulesOn(string $weekday): Collection
    {
        return $this->orderedSchedules()->where('weekday', $weekday)->values();
    }

    /**
     * "Mandag og Onsdag 17:00–18:30" when the days share an hour, and
     * "Mandag 17:00–18:30 · Onsdag 19:00–20:00" when they do not.
     */
    public function scheduleLabel(): ?string
    {
        $schedules = $this->orderedSchedules();
        if ($schedules->isEmpty()) return null;

        $groups = [];
        foreach ($schedules as $s) {
            $groups[$s->timeRange() ?? ''][] = $s->weekday;
        }

        $parts = [];
        foreach ($groups as $time => $days) {
            $label = $this->daysLabel(array_values(array_unique($days)));
            $parts[] = trim($label . ($time !== '' ? ' ' . $time : ''));
        }

        return implode(' · ', $parts) ?: null;
    }

    /** The shared time range, or null when the days differ. */
    public function timeRange(): ?string
    {
        $ranges = $this->orderedSchedules()->map(fn (CourseSchedule $s) => $s->timeRange())->unique();
        return $ranges->count() === 1 ? $ranges->first() : null;
    }

    public function runsOn(Carbon $day): bool
    {
        return in_array(self::weekdayKey($day), $this->weekdaysList(), true);
    }

    /**
     * The next slot this course actually runs, skipping the given YYYY-MM-DD
     * dates (cancellations). A slot counts as "next" until it ends, so a course
     * does not jump forward the minute it starts.
     *
     * @param  array<string> $skipDates
     */
    public function nextOccurrence(?Carbon $from = null, array $skipDates = []): ?CourseOccurrence
    {
        if (! $this->weekdaysList()) return null;

        $from = $from ? $from->copy() : Carbon::now();
        $skip = array_flip($skipDates);

        // Two weeks clears a full cycle plus any run of cancelled sessions.
        for ($i = 0; $i <= 14; $i++) {
            $day = $from->copy()->addDays($i)->startOfDay();
            if (isset($skip[$day->toDateString()])) continue;

            foreach ($this->schedulesOn(self::weekdayKey($day)) as $slot) {
                $end = $slot->endOn($day);
                if ($end->lt($from)) continue;
                return new CourseOccurrence($slot->startOn($day), $end, $slot);
            }
        }

        return null;
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

    public function isFaellestraening(): bool { return $this->type === self::TYPE_FAELLES; }
    public function isPersonlig(): bool { return $this->type === self::TYPE_PERSONLIG; }
    public function typeLabel(): string { return self::TYPES[$this->type] ?? self::TYPES[self::TYPE_HOLD]; }
    public function scopeHold($q) { return $q->where('type', self::TYPE_HOLD); }
    public function scopeFaellestraening($q) { return $q->where('type', self::TYPE_FAELLES); }
    public function scopePersonlig($q) { return $q->where('type', self::TYPE_PERSONLIG); }

    /** A paid, recurring hold — the thing a membership is. Fællestræning never is. */
    public function scopePaidMembership($q)
    {
        return $q->where('type', '!=', self::TYPE_FAELLES)
            ->where('price_cents', '>', 0)
            ->where('free_enrollment', false);
    }

    /** Nobody signs up for a fællestræning: paying members are simply welcome. */
    public function allowsEnrollment(): bool { return ! $this->isFaellestraening(); }
    public function hasMemberList(): bool { return ! $this->isFaellestraening(); }
    public function hasChat(): bool { return ! $this->isFaellestraening() || $this->chat_enabled; }

    /**
     * May this user open the training's inner pages (chat, medier)?
     * A hold asks "are you enrolled"; a fællestræning has no enrollments, so it
     * asks "do you pay us monthly for anything" instead.
     */
    public function grantsAccessTo(?User $user): bool
    {
        if (! $user) return false;
        if ($user->isOwner() || $this->hasTrainer($user)) return true;

        return $this->isFaellestraening()
            ? $user->hasPaidMembership()
            : $user->enrolledIn($this);
    }

    public function activeCount(): int { return $this->activeEnrollments()->count(); }
    /** Everyone the hold still owes something to — including a member whose payment is failing. */
    public function memberCount(): int { return $this->enrollments()->whereIn('status', ['active', 'past_due', 'pending'])->count(); }
    // Capacity is unknowable for a fællestræning — nobody registers, so there is
    // nothing to count and the session is never "full".
    public function isFull(): bool { return $this->allowsEnrollment() && $this->activeCount() >= $this->max_participants; }
    public function slotsLeft(): int { return max(0, $this->max_participants - $this->activeCount()); }
}
