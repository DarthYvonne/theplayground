<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseCancellation;
use App\Models\Enrollment;
use App\Models\Message;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MineCoursesPageTest extends TestCase
{
    use RefreshDatabase;

    /** @param string|array<string, array{0:?string,1:?string}> $weekdays */
    private function course(string $title, string|array $weekdays, string $start = '17:00', string $end = '18:30'): Course
    {
        $course = Course::create([
            'title' => $title,
            'description' => 'x',
            'price_cents' => 0,
            'free_enrollment' => true,
            'is_active' => true,
        ]);

        $slots = is_array($weekdays)
            ? $weekdays
            : array_fill_keys(array_filter(explode(',', $weekdays)), [$start, $end]);

        foreach ($slots as $day => [$from, $to]) {
            $course->schedules()->create(['weekday' => $day, 'start_time' => $from, 'end_time' => $to]);
        }

        return $course->fresh();
    }

    private function enroll(User $user, Course $course, string $status = 'active'): Enrollment
    {
        return Enrollment::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'status' => $status,
            'enrolled_at' => now(),
        ]);
    }

    public function test_next_occurrence_label_and_ordering(): void
    {
        // Monday 08:00.
        Carbon::setTestNow(Carbon::parse('2026-08-03 08:00:00'));

        $user = User::factory()->create(['role' => 'user']);
        $wednesday = $this->course('Onsdagshold', 'wed');
        $today = $this->course('Mandagshold', 'mon');
        $tomorrow = $this->course('Tirsdagshold', 'tue');

        $this->enroll($user, $wednesday);
        $this->enroll($user, $today);
        $this->enroll($user, $tomorrow);

        $res = $this->actingAs($user)->get('/hold/dine')->assertOk();

        $res->assertSee('I dag kl. 17:00');
        $res->assertSee('I morgen kl. 17:00');
        $res->assertSee('Onsdag kl. 17:00');

        $html = $res->getContent();
        $this->assertLessThan(strpos($html, 'Tirsdagshold'), strpos($html, 'Mandagshold'));
        $this->assertLessThan(strpos($html, 'Onsdagshold'), strpos($html, 'Tirsdagshold'));
    }

    public function test_session_stays_today_until_it_ends_then_rolls_to_next_week(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $course = $this->course('Mandagshold', 'mon');
        $this->enroll($user, $course);

        // Mid-session on Monday.
        Carbon::setTestNow(Carbon::parse('2026-08-03 17:30:00'));
        $this->actingAs($user)->get('/hold/dine')->assertOk()->assertSee('I dag kl. 17:00');

        // After it ended: rolls to next week, which is a full 7 days out and so
        // shows as a date — "Mandag" that far ahead would read as tomorrow-ish.
        Carbon::setTestNow(Carbon::parse('2026-08-03 19:00:00'));
        $this->actingAs($user)->get('/hold/dine')->assertOk()->assertSee('10.08. kl. 17:00');
    }

    public function test_cancelled_session_shows_and_is_skipped_for_next(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-03 08:00:00'));

        $user = User::factory()->create(['role' => 'user']);
        $course = $this->course('Mandagshold', 'mon');
        $this->enroll($user, $course);

        CourseCancellation::create([
            'course_id' => $course->id,
            'occurrence_date' => '2026-08-03',
            'reason' => 'Sygdom',
        ]);

        $res = $this->actingAs($user)->get('/hold/dine')->assertOk();
        $res->assertSee('Aflyst i dag');
        // Skips today and lands on the following Monday.
        $res->assertSee('Næste: 10.08. kl. 17:00');
    }

    public function test_past_due_enrollment_still_listed_with_payment_warning(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-03 08:00:00'));

        $user = User::factory()->create(['role' => 'user']);
        $course = $this->course('Betalingshold', 'mon');
        $this->enroll($user, $course, 'past_due');

        $res = $this->actingAs($user)->get('/hold/dine')->assertOk();
        $res->assertSee('Betalingshold');
        $res->assertSee('Betaling mangler');
        $res->assertSee('Der mangler betaling');
        $res->assertSee('Dine hold (1)');
    }

    public function test_unread_course_messages_are_counted_per_course(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-03 08:00:00'));

        $user = User::factory()->create(['role' => 'user']);
        $other = User::factory()->create(['role' => 'user']);
        $course = $this->course('Mandagshold', 'mon');
        $this->enroll($user, $course);

        foreach (range(1, 3) as $i) {
            Message::create([
                'channel_type' => 'course',
                'course_id' => $course->id,
                'user_id' => $other->id,
                'body' => "besked $i",
            ]);
        }
        // Own messages never count as unread.
        Message::create([
            'channel_type' => 'course',
            'course_id' => $course->id,
            'user_id' => $user->id,
            'body' => 'min egen',
        ]);

        $this->actingAs($user)->get('/hold/dine')->assertOk()->assertSee('3 nye');
    }

    public function test_empty_state_when_not_enrolled(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)->get('/hold/dine')
            ->assertOk()
            ->assertSee('Du er ikke tilmeldt noget endnu')
            ->assertSee('Dine hold (0)');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
