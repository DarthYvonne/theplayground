<?php

namespace App\Payments;

use App\Mail\PaymentFailedMail;
use App\Models\AppNotification;
use App\Models\Enrollment;
use Illuminate\Support\Facades\Mail;

/** Shared past-due notification used by both payment webhooks. */
class EnrollmentNotifier
{
    /**
     * In-app + email notification when an enrollment goes past_due. Callers must
     * guard on a real status transition so the same failure can't notify twice.
     */
    public static function notifyPastDue(Enrollment $enrollment): void
    {
        $enrollment->loadMissing('user', 'course');
        $user = $enrollment->user;
        $course = $enrollment->course;
        if (! $user || ! $course) {
            return;
        }

        AppNotification::create([
            'user_id' => $user->id,
            'type' => 'system',
            'title' => 'Betaling fejlede',
            'body' => 'Din betaling for "'.$course->title.'" gik ikke igennem. Forny din betaling for at bevare adgangen.',
            'link' => route('profile.billing'),
            'course_id' => $course->id,
        ]);

        if ($user->email) {
            try {
                Mail::to($user->email)->queue(new PaymentFailedMail($user, $course));
            } catch (\Throwable) {
                // Don't fail the webhook on mail issues — the in-app notice still surfaces it.
            }
        }
    }
}
