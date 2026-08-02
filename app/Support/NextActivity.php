<?php

namespace App\Support;

use App\Models\Course;
use App\Models\CourseCancellation;
use App\Models\FloatingBooking;
use App\Models\User;
use Carbon\Carbon;

/**
 * The one thing a user is due at next — a float booking, or a training they
 * attend or teach, whichever comes first.
 *
 * Strictly upcoming: a session already running is deliberately skipped, since
 * "Næste" pointing at something you are presumably already standing in would be
 * useless. Cancelled dates are skipped too.
 */
class NextActivity
{
    private function __construct(
        public readonly string $kind,
        public readonly string $title,
        public readonly string $when,
        public readonly string $url,
        public readonly Carbon $start,
    ) {}

    public static function for(?User $user): ?self
    {
        if (! $user) return null;

        $now = Carbon::now();
        $candidates = array_values(array_filter([
            self::nextFloat($user, $now),
            self::nextCourse($user, $now),
        ]));
        if (! $candidates) return null;

        usort($candidates, fn (self $a, self $b) => $a->start <=> $b->start);

        return $candidates[0];
    }

    private static function nextFloat(User $user, Carbon $now): ?self
    {
        $booking = FloatingBooking::where('user_id', $user->id)
            ->where('status', 'active')
            ->where('slot_start', '>', $now)
            ->orderBy('slot_start')
            ->first();

        if (! $booking) return null;

        return new self(
            kind: 'Float',
            title: 'Floating',
            when: self::whenLabel($booking->slot_start, $now),
            url: route('floating.index'),
            start: $booking->slot_start,
        );
    }

    private static function nextCourse(User $user, Carbon $now): ?self
    {
        $courses = Course::with(['schedules', 'trainers', 'member'])
            ->where('is_active', true)
            ->visibleTo($user)
            ->get();

        // Same notion of "mine" the calendar uses: only a hold is something you
        // sign up for — a trainer teaches, a fællestræning comes with the
        // membership, and a 1:1 is held by name.
        $joined = array_flip($user->enrollments()->where('status', 'active')->withinPeriod()->pluck('course_id')->all());
        $paid = $user->hasPaidMembership();

        $mine = $courses->filter(fn (Course $c) => isset($joined[$c->id])
            || $c->trainers->contains('id', $user->id)
            || ($c->isFaellestraening() && $paid)
            || ($c->isPersonlig() && $c->member_id === $user->id));

        if ($mine->isEmpty()) return null;

        // Two weeks clears a full cycle plus any run of cancelled sessions,
        // matching what Course::nextOccurrence() is willing to look through.
        $skipByCourse = [];
        foreach (CourseCancellation::mapForRange($mine->pluck('id')->all(), $now->copy()->startOfDay(), $now->copy()->addDays(15)) as $key => $_) {
            [$courseId, $date] = explode(':', $key);
            $skipByCourse[(int) $courseId][] = $date;
        }

        $best = null;
        foreach ($mine as $course) {
            $skip = $skipByCourse[$course->id] ?? [];

            $occurrence = $course->nextOccurrence($now, $skip);
            // nextOccurrence() counts a slot as "next" until it ends, so one that
            // has already started has to be stepped past.
            if ($occurrence && $occurrence->start->lte($now)) {
                $occurrence = $course->nextOccurrence($occurrence->end->copy()->addMinute(), $skip);
            }
            if (! $occurrence) continue;

            if (! $best || $occurrence->start->lt($best[1]->start)) {
                $best = [$course, $occurrence];
            }
        }

        if (! $best) return null;
        [$course, $occurrence] = $best;

        return new self(
            kind: $course->typeLabel(),
            title: self::titleFor($course, $user),
            when: $occurrence->label($now),
            url: route('courses.show', $course),
            start: $occurrence->start,
        );
    }

    /**
     * A personlig træning stores its type in its own title ("Personlig træning —
     * Anders & Mette"), which reads as a stutter under a "Næste: Personlig
     * træning" heading. Name the other person instead — you already know you are
     * the one attending.
     */
    private static function titleFor(Course $course, User $user): string
    {
        if (! $course->isPersonlig()) return $course->title;

        $other = $course->trainers->contains('id', $user->id)
            ? $course->member?->name
            : $course->primaryTrainer()?->name;

        return $other ? 'Med '.$other : $course->title;
    }

    /** "I dag kl. 17:00" / "I morgen kl. 17:00" / "Onsdag kl. 17:00" / "12.08. kl. 17:00". */
    private static function whenLabel(Carbon $start, Carbon $now): string
    {
        $time = ' kl. '.$start->format('H:i');
        $days = (int) $now->copy()->startOfDay()->diffInDays($start->copy()->startOfDay(), false);

        if ($days === 0) return 'I dag'.$time;
        if ($days === 1) return 'I morgen'.$time;
        if ($days < 7) return Course::WEEKDAYS[Course::weekdayKey($start)].$time;

        return $start->format('d.m.').$time;
    }
}
