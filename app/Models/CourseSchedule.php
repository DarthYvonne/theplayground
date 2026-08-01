<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One slot in a course's week: "Mandag 17:00–18:30". A course has one row per
 * slot, so different weekdays can run at different times.
 */
class CourseSchedule extends Model
{
    public $timestamps = false;

    protected $fillable = ['course_id', 'weekday', 'start_time', 'end_time'];

    public function course(): BelongsTo { return $this->belongsTo(Course::class); }

    public function weekdayName(): string
    {
        return Course::WEEKDAYS[$this->weekday] ?? $this->weekday;
    }

    public function timeRange(): ?string
    {
        if (! $this->start_time && ! $this->end_time) return null;
        $fmt = fn ($t) => $t ? substr((string) $t, 0, 5) : '';
        if ($this->start_time && $this->end_time) return $fmt($this->start_time) . '–' . $fmt($this->end_time);
        return $fmt($this->start_time ?: $this->end_time);
    }

    public function startsAt(): ?string
    {
        return $this->start_time ? substr((string) $this->start_time, 0, 5) : null;
    }

    /** This slot's start on a given date; midnight when the slot has no time. */
    public function startOn(Carbon $day): Carbon
    {
        return $this->at($day, $this->start_time, '00:00');
    }

    /** This slot's end on a given date; end of day when the slot has no time. */
    public function endOn(Carbon $day): Carbon
    {
        $end = $this->at($day, $this->end_time ?: $this->start_time, '23:59');
        $start = $this->startOn($day);
        return $end->lt($start) ? $start : $end;
    }

    private function at(Carbon $day, ?string $time, string $fallback): Carbon
    {
        $parts = explode(':', $time ? substr((string) $time, 0, 5) : $fallback);
        return $day->copy()->startOfDay()->setTime((int) ($parts[0] ?? 0), (int) ($parts[1] ?? 0));
    }
}
