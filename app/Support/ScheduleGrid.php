<?php

namespace App\Support;

use App\Models\Course;
use App\Models\CourseSchedule;

/**
 * Groups courses into per-weekday slot lists for the calendar views. Entries are
 * CourseSchedule rows rather than courses, so a course running Monday 17:00 and
 * Wednesday 19:00 renders at the right hour on each day.
 */
class ScheduleGrid
{
    /**
     * @param  iterable<Course> $courses
     * @param  array<string> $weekdayKeys
     * @return array<string, array<int, CourseSchedule>>
     */
    public static function byDay(iterable $courses, array $weekdayKeys): array
    {
        $byDay = array_fill_keys($weekdayKeys, []);

        foreach ($courses as $course) {
            foreach ($course->orderedSchedules() as $slot) {
                if (! isset($byDay[$slot->weekday])) continue;
                $slot->setRelation('course', $course);
                $byDay[$slot->weekday][] = $slot;
            }
        }

        foreach ($byDay as $day => $slots) {
            usort($byDay[$day], fn (CourseSchedule $a, CourseSchedule $b) => [$a->start_time ?? '', $a->course->title] <=> [$b->start_time ?? '', $b->course->title]);
        }

        return $byDay;
    }
}
