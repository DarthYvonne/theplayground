<?php

namespace App\Support;

use App\Models\Course;
use App\Models\CourseSchedule;
use Carbon\Carbon;

/**
 * One dated run of a course — a schedule slot resolved onto a real date.
 * Carries its own time, so a course running Monday 17:00 and Wednesday 19:00
 * reports the right hour for whichever day comes next.
 */
class CourseOccurrence
{
    public function __construct(
        public readonly Carbon $start,
        public readonly Carbon $end,
        public readonly CourseSchedule $schedule,
    ) {}

    public function hasTime(): bool
    {
        return $this->schedule->start_time !== null;
    }

    public function timeRange(): ?string
    {
        return $this->schedule->timeRange();
    }

    /** "I dag kl. 17:00" / "I morgen kl. 17:00" / "Onsdag kl. 17:00" / "12.08. kl. 17:00". */
    public function label(?Carbon $now = null): string
    {
        $now = $now ? $now->copy() : Carbon::now();
        $time = $this->hasTime() ? ' kl. ' . $this->start->format('H:i') : '';
        $days = (int) $now->copy()->startOfDay()->diffInDays($this->start->copy()->startOfDay(), false);

        if ($days === 0) return 'I dag' . $time;
        if ($days === 1) return 'I morgen' . $time;
        if ($days < 7) return Course::WEEKDAYS[Course::weekdayKey($this->start)] . $time;
        return $this->start->format('d.m.') . $time;
    }
}
