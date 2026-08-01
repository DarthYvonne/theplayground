<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseScheduleTest extends TestCase
{
    use RefreshDatabase;

    /** @param array<string, array{0:?string,1:?string}> $slots */
    private function course(array $slots, string $title = 'Hold'): Course
    {
        $course = Course::create([
            'title' => $title,
            'description' => 'x',
            'price_cents' => 0,
            'is_active' => true,
            'max_participants' => 10,
        ]);
        foreach ($slots as $day => [$start, $end]) {
            $course->schedules()->create(['weekday' => $day, 'start_time' => $start, 'end_time' => $end]);
        }

        return $course->fresh();
    }

    public function test_days_with_different_hours_are_kept_apart(): void
    {
        $course = $this->course(['mon' => ['17:00', '18:30'], 'wed' => ['19:00', '20:00']]);

        $this->assertSame('Mandag 17:00–18:30 · Onsdag 19:00–20:00', $course->scheduleLabel());
        $this->assertNull($course->timeRange(), 'no single range spans both days');
        $this->assertSame(['mon', 'wed'], $course->weekdaysList());
    }

    public function test_days_sharing_an_hour_stay_grouped(): void
    {
        $course = $this->course(['mon' => ['17:00', '18:30'], 'wed' => ['17:00', '18:30']]);

        $this->assertSame('Mandag og Onsdag 17:00–18:30', $course->scheduleLabel());
        $this->assertSame('17:00–18:30', $course->timeRange());
    }

    public function test_next_occurrence_reports_the_hour_of_the_day_it_lands_on(): void
    {
        $course = $this->course(['mon' => ['17:00', '18:30'], 'wed' => ['19:00', '20:00']]);

        // Monday morning → Monday's slot.
        $now = Carbon::parse('2026-08-03 08:00:00');
        $this->assertSame('I dag kl. 17:00', $course->nextOccurrence($now)->label($now));

        // Monday night, after the session → Wednesday's, at Wednesday's hour.
        $now = Carbon::parse('2026-08-03 21:00:00');
        $this->assertSame('Onsdag kl. 19:00', $course->nextOccurrence($now)->label($now));
    }

    public function test_two_slots_on_the_same_day_are_both_kept(): void
    {
        $course = $this->course(['tue' => ['09:00', '10:00']]);
        $course->schedules()->create(['weekday' => 'tue', 'start_time' => '18:00', 'end_time' => '19:00']);
        $course->refresh();

        $this->assertCount(2, $course->schedulesOn('tue'));
        $this->assertSame(['tue'], $course->weekdaysList());

        // Between the two, the evening slot is next.
        $now = Carbon::parse('2026-08-04 12:00:00');
        $this->assertSame('I dag kl. 18:00', $course->nextOccurrence($now)->label($now));
    }

    public function test_a_day_without_an_hour_still_counts_as_scheduled(): void
    {
        $course = $this->course(['fri' => [null, null]]);

        $this->assertSame('Fredag', $course->scheduleLabel());
        $now = Carbon::parse('2026-08-03 08:00:00');
        $this->assertSame('Fredag', $course->nextOccurrence($now)->label($now), 'no time, so no "kl."');
    }

    public function test_admin_form_saves_a_time_per_day(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($owner)->post(route('admin.courses.store'), [
            'title' => 'Split hold',
            'description' => 'x',
            'trainer_ids' => [$owner->id],
            'price_kr' => 100,
            'max_participants' => 10,
            'slots' => [
                ['weekday' => 'wed', 'start' => '19:00', 'end' => '20:00'],
                ['weekday' => 'mon', 'start' => '17:00', 'end' => '18:30'],
            ],
        ])->assertSessionHasNoErrors();

        $course = Course::where('title', 'Split hold')->firstOrFail();
        // Stored in calendar order regardless of the order they were added.
        $this->assertSame(['mon', 'wed'], $course->weekdaysList());
        $this->assertSame('17:00–18:30', $course->schedulesOn('mon')->first()->timeRange());
        $this->assertSame('19:00–20:00', $course->schedulesOn('wed')->first()->timeRange());
    }

    public function test_editing_replaces_the_previous_slots(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $course = $this->course(['mon' => ['17:00', '18:30'], 'wed' => ['19:00', '20:00']], 'Hold B');
        $course->trainers()->sync([$owner->id]);

        $this->actingAs($owner)->post(route('admin.courses.update', $course), [
            'title' => 'Hold B',
            'description' => 'x',
            'trainer_ids' => [$owner->id],
            'price_kr' => 0,
            'max_participants' => 10,
            'slots' => [['weekday' => 'thu', 'start' => '20:00', 'end' => '21:00']],
        ])->assertSessionHasNoErrors();

        $course->refresh();
        $this->assertSame(['thu'], $course->weekdaysList());
        $this->assertSame(1, $course->schedules()->count());
    }

    public function test_an_end_before_the_start_is_dropped_rather_than_stored(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($owner)->post(route('admin.courses.store'), [
            'title' => 'Bagvendt',
            'description' => 'x',
            'trainer_ids' => [$owner->id],
            'price_kr' => 0,
            'max_participants' => 10,
            'slots' => [['weekday' => 'mon', 'start' => '18:00', 'end' => '17:00']],
        ])->assertSessionHasNoErrors();

        $slot = Course::where('title', 'Bagvendt')->firstOrFail()->schedulesOn('mon')->first();
        $this->assertSame('18:00', $slot->startsAt());
        $this->assertNull($slot->end_time);
    }

    public function test_calendar_places_a_course_at_the_right_hour_on_each_day(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $course = $this->course(['mon' => ['17:00', '18:30'], 'wed' => ['19:00', '20:00']], 'Delt hold');
        $course->trainers()->sync([User::factory()->create(['role' => 'trainer'])->id]);

        $html = $this->actingAs($user)->get(route('home.calendar', ['view' => 'week']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('17:00–18:30', $html);
        $this->assertStringContainsString('19:00–20:00', $html);
    }

    public function test_two_slots_on_the_same_day_can_be_added(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($owner)->post(route('admin.courses.store'), [
            'title' => 'Morgen og aften',
            'description' => 'x',
            'trainer_ids' => [$owner->id],
            'price_kr' => 0,
            'max_participants' => 10,
            'slots' => [
                ['weekday' => 'tue', 'start' => '18:00', 'end' => '19:00'],
                ['weekday' => 'tue', 'start' => '09:00', 'end' => '10:00'],
            ],
        ])->assertSessionHasNoErrors();

        $slots = Course::where('title', 'Morgen og aften')->firstOrFail()->schedulesOn('tue');
        $this->assertCount(2, $slots);
        $this->assertSame('09:00–10:00', $slots->first()->timeRange());
    }

    public function test_the_same_day_and_start_is_not_stored_twice(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($owner)->post(route('admin.courses.store'), [
            'title' => 'Dublet',
            'description' => 'x',
            'trainer_ids' => [$owner->id],
            'price_kr' => 0,
            'max_participants' => 10,
            'slots' => [
                ['weekday' => 'mon', 'start' => '17:00', 'end' => '18:00'],
                ['weekday' => 'mon', 'start' => '17:00', 'end' => '18:00'],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertCount(1, Course::where('title', 'Dublet')->firstOrFail()->schedulesOn('mon'));
    }

    public function test_an_unknown_weekday_is_rejected(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($owner)->post(route('admin.courses.store'), [
            'title' => 'Ugyldig',
            'description' => 'x',
            'trainer_ids' => [$owner->id],
            'price_kr' => 0,
            'max_participants' => 10,
            'slots' => [['weekday' => 'funday', 'start' => '17:00', 'end' => '18:00']],
        ])->assertSessionHasErrors('slots.0.weekday');

        $this->assertSame(0, Course::where('title', 'Ugyldig')->count());
    }

    /**
     * Rolls the schema back to the single-time-pair shape, writes a course the
     * old way, then migrates forward — the exact path production takes.
     */
    public function test_migration_backfills_existing_courses_into_slots(): void
    {
        $this->artisan('migrate:rollback', ['--step' => 1])->assertSuccessful();

        $id = \DB::table('courses')->insertGetId([
            'title' => 'Gammelt hold',
            'description' => 'x',
            'price_cents' => 0,
            'max_participants' => 10,
            'is_active' => 1,
            'weekdays' => 'mon,wed',
            'start_time' => '17:00',
            'end_time' => '18:30',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('migrate')->assertSuccessful();

        $course = Course::findOrFail($id);
        $this->assertSame(['mon', 'wed'], $course->weekdaysList());
        $this->assertSame('17:00–18:30', $course->schedulesOn('mon')->first()->timeRange());
        $this->assertSame('17:00–18:30', $course->schedulesOn('wed')->first()->timeRange());
        $this->assertSame('Mandag og Onsdag 17:00–18:30', $course->scheduleLabel());
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
