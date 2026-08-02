<?php

namespace App\Support;

use App\Models\AppNotification;
use App\Models\Course;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Links a personlig træning that was set up before its member had an account.
 *
 * The trainer types an email or a phone number; when someone registers or logs
 * in with matching details, the training becomes theirs. Neither identifier is
 * verified by this application, so three things keep a wrong claim cheap:
 *
 *  - an already-claimed training is never re-claimed (member_id must be null),
 *  - Course::grantsAccessTo() still demands a live enrollment, so a claimant
 *    who has not paid sees only the trainer, the time and the price,
 *  - every claim notifies the trainers, which is the only way anyone finds out.
 */
class PersonligClaim
{
    /** @return Collection<int, Course> the trainings that were claimed */
    public static function claimFor(User $user): Collection
    {
        $email = Contact::normalizeEmail($user->email);
        $phone = Contact::normalizePhone($user->phone);

        if (! $email && ! $phone) {
            return collect();
        }

        $courses = Course::query()
            ->personlig()
            // Never take one that already belongs to somebody.
            ->whereNull('member_id')
            ->where(function ($q) use ($email, $phone) {
                // lower() on both sides: sqlite compares varchars case-sensitively,
                // MySQL's default collation does not. Match identically on each.
                if ($email) $q->whereRaw('lower(member_invite_email) = ?', [$email]);
                if ($phone) $q->orWhere('member_invite_phone', $phone);
            })
            ->with('trainers')
            ->get();

        foreach ($courses as $course) {
            $course->forceFill([
                'member_id' => $user->id,
                'member_claimed_at' => now(),
            ])->save();
            $course->setRelation('member', $user);
            $course->refreshPersonligTitle();
            self::notifyTrainers($course, $user);
        }

        return $courses;
    }

    /**
     * The only detection channel for a claim that went to the wrong person.
     */
    private static function notifyTrainers(Course $course, User $member): void
    {
        $trainerIds = $course->trainers->pluck('id')->reject(fn ($id) => $id === $member->id)->values();
        if ($trainerIds->isEmpty()) {
            return;
        }

        $now = now();
        AppNotification::insert($trainerIds->map(fn ($id) => [
            'user_id' => $id,
            'type' => 'system',
            'title' => $member->name.' har fået adgang til '.$course->title,
            'body' => 'Oprettet til '.($course->member_invite_email ?: $course->member_invite_phone).'.',
            'link' => route('courses.show', $course),
            'course_id' => $course->id,
            'actor_id' => $member->id,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all());
    }
}
