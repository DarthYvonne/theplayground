<?php

namespace App\Support;

use App\Models\Course;

/**
 * A trainer can only be in one place at a time — whether the two places are a
 * hold, a fællestræning or a personlig træning.
 *
 * Schedules recur weekly with no end date, so an overlap is never a one-off
 * collision on some date: it repeats for as long as both courses exist. That is
 * why it blocks the save outright rather than warning.
 */
class TrainerClash
{
    /**
     * The first slot that would put one of these trainers in two places at once,
     * as a ready-to-show Danish sentence. Null when the week is free.
     *
     * Only active courses count as occupied — a passive hold is not running, so
     * its trainer is not busy. The course being edited is skipped so that
     * re-saving it does not clash with itself.
     *
     * @param  array<int> $trainerIds
     * @param  array<int, array{weekday:string, start_time:?string, end_time:?string}> $slots  in calendar order, as slotsFrom() returns them
     */
    public static function find(array $trainerIds, array $slots, ?Course $ignore = null): ?string
    {
        if (! $trainerIds || ! $slots) return null;

        $booked = Course::query()
            ->where('is_active', true)
            ->when($ignore?->exists, fn ($q) => $q->whereKeyNot($ignore->getKey()))
            ->whereHas('trainers', fn ($t) => $t->whereIn('users.id', $trainerIds))
            // Narrowed to the people being assigned, so the message names whoever
            // is actually double-booked rather than the whole teaching team.
            ->with(['schedules', 'trainers' => fn ($t) => $t->whereIn('users.id', $trainerIds)])
            ->get();

        foreach ($slots as $i => $slot) {
            $range = self::range($slot['start_time'] ?? null, $slot['end_time'] ?? null);

            // The proposed week must hold together on its own first: two of its
            // slots can overlap each other without touching another course.
            foreach (array_slice($slots, $i + 1) as $other) {
                if ($other['weekday'] !== $slot['weekday']) continue;
                if (! self::overlaps($range, self::range($other['start_time'] ?? null, $other['end_time'] ?? null))) continue;

                return sprintf(
                    'De to tidspunkter %s overlapper hinanden. Vælg et andet tidspunkt.',
                    self::dayName($slot['weekday'])
                );
            }

            foreach ($booked as $course) {
                foreach ($course->schedules as $taken) {
                    if ($taken->weekday !== $slot['weekday']) continue;
                    if (! self::overlaps($range, self::range($taken->start_time, $taken->end_time))) continue;

                    return sprintf(
                        '%s træner allerede %s %s %s. Vælg et andet tidspunkt.',
                        $course->trainers->pluck('name')->join(' og '),
                        $course->title,
                        self::dayName($taken->weekday),
                        $taken->timeRange()
                    );
                }
            }
        }

        return null;
    }

    /**
     * A slot with no start time is "that weekday, hour to be decided" — there is
     * nothing to compare, so it never clashes.
     *
     * @param  array{0:string, 1:string}|null $a
     * @param  array{0:string, 1:string}|null $b
     */
    private static function overlaps(?array $a, ?array $b): bool
    {
        if (! $a || ! $b) return false;

        [$aStart, $aEnd] = $a;
        [$bStart, $bEnd] = $b;

        // An open-ended slot ("starts 17:00", no end) is a single point in the
        // day, matching CourseSchedule::endOn()'s fallback. Two of them meet only
        // at the same minute; one of them clashes when it lands inside the
        // other's range. Real ranges compare half-open, so 17:00–18:00 and
        // 18:00–19:00 stay back-to-back rather than colliding.
        if ($aStart === $aEnd && $bStart === $bEnd) return $aStart === $bStart;
        if ($aStart === $aEnd) return $aStart >= $bStart && $aStart < $bEnd;
        if ($bStart === $bEnd) return $bStart >= $aStart && $bStart < $aEnd;

        return $aStart < $bEnd && $bStart < $aEnd;
    }

    /** Zero-padded HH:MM compares as a string. @return array{0:string, 1:string}|null */
    private static function range(?string $start, ?string $end): ?array
    {
        $hhmm = fn (?string $t) => $t ? substr($t, 0, 5) : null;

        $start = $hhmm($start);
        if (! $start) return null;
        $end = $hhmm($end);

        return [$start, $end && $end > $start ? $end : $start];
    }

    private static function dayName(string $weekday): string
    {
        return mb_strtolower(Course::WEEKDAYS[$weekday] ?? $weekday);
    }
}
