<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaellestraeningTest extends TestCase
{
    use RefreshDatabase;

    private function course(string $title, string $type, array $attrs = []): Course
    {
        $course = Course::create(array_merge([
            'title' => $title,
            'type' => $type,
            'description' => 'x',
            'price_cents' => $type === Course::TYPE_FAELLES ? 0 : 30000,
            'is_active' => true,
            'max_participants' => 10,
        ], $attrs));
        $course->schedules()->create(['weekday' => 'mon', 'start_time' => '17:00', 'end_time' => '18:00']);

        return $course->fresh();
    }

    /** A member paying monthly for a hold — the thing that makes fællestræning free. */
    private function payingMember(string $status = 'active'): User
    {
        $user = User::factory()->create(['role' => 'user']);
        Enrollment::create([
            'user_id' => $user->id,
            'course_id' => $this->course('Betalt hold', Course::TYPE_HOLD)->id,
            'status' => $status,
            'enrolled_at' => now(),
        ]);

        return $user;
    }

    /* ------------------------------------------------------------ listing -- */

    public function test_each_type_is_listed_on_its_own_page_only(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $this->course('Almindeligt hold', Course::TYPE_HOLD);
        $this->course('Fælles morgentræning', Course::TYPE_FAELLES);
        $this->course('En-til-en forløb', Course::TYPE_PERSONLIG);

        $this->actingAs($user)->get(route('faellestraening.index'))
            ->assertOk()->assertSee('Fælles morgentræning')
            ->assertDontSee('Almindeligt hold')->assertDontSee('En-til-en forløb');

        $this->actingAs($user)->get(route('catalog'))
            ->assertOk()->assertSee('Almindeligt hold')
            ->assertDontSee('Fælles morgentræning')->assertDontSee('En-til-en forløb');

        $this->actingAs($user)->get(route('personlig.index'))
            ->assertOk()->assertSee('En-til-en forløb')
            ->assertDontSee('Almindeligt hold')->assertDontSee('Fælles morgentræning');
    }

    public function test_the_menu_groups_all_three_under_traening(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)->get(route('faellestraening.index'))
            ->assertOk()
            ->assertSee('Træning')
            ->assertSee(route('catalog'))
            ->assertSee(route('faellestraening.index'))
            ->assertSee(route('personlig.index'))
            ->assertSee(route('home.calendar'));
    }

    /* --------------------------------------------------------- enrollment -- */

    public function test_nobody_can_enroll_in_a_faellestraening(): void
    {
        $course = $this->course('Fælles', Course::TYPE_FAELLES);
        $user = $this->payingMember();

        $this->actingAs($user)->post(route('enroll', $course))->assertNotFound();
        $this->actingAs($user)->post(route('enroll.card', $course))->assertNotFound();
        $this->assertSame(0, Enrollment::where('course_id', $course->id)->count());
    }

    public function test_a_faellestraening_page_offers_no_checkout_and_no_capacity(): void
    {
        $course = $this->course('Fælles', Course::TYPE_FAELLES);

        $this->actingAs($this->payingMember())->get(route('courses.show', $course))
            ->assertOk()
            ->assertSee('Gratis for dig')
            ->assertDontSee('Betal med MobilePay')
            ->assertDontSee('tilmeldt');
    }

    public function test_a_member_without_a_paid_membership_is_told_what_it_costs(): void
    {
        $course = $this->course('Fælles', Course::TYPE_FAELLES);
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)->get(route('courses.show', $course))
            ->assertOk()
            ->assertSee('løbende medlemskab')
            ->assertDontSee('Gratis for dig');
    }

    public function test_it_has_no_member_list(): void
    {
        $course = $this->course('Fælles', Course::TYPE_FAELLES);
        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($owner)->get(route('courses.members', $course))->assertNotFound();
    }

    /* ------------------------------------------------------------- access -- */

    public function test_a_paying_member_reaches_the_chat_without_being_enrolled(): void
    {
        $course = $this->course('Fælles', Course::TYPE_FAELLES);

        $this->actingAs($this->payingMember())->get(route('chat.course', $course))->assertOk();
    }

    public function test_a_failing_payment_does_not_remove_access(): void
    {
        $course = $this->course('Fælles', Course::TYPE_FAELLES);

        $this->actingAs($this->payingMember('past_due'))->get(route('chat.course', $course))->assertOk();
    }

    public function test_a_user_who_pays_nothing_is_kept_out(): void
    {
        $course = $this->course('Fælles', Course::TYPE_FAELLES);
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)->get(route('chat.course', $course))->assertForbidden();
    }

    public function test_a_free_hold_does_not_count_as_a_membership(): void
    {
        $course = $this->course('Fælles', Course::TYPE_FAELLES);
        $user = User::factory()->create(['role' => 'user']);
        Enrollment::create([
            'user_id' => $user->id,
            'course_id' => $this->course('Gratis hold', Course::TYPE_HOLD, ['price_cents' => 0])->id,
            'status' => 'active',
            'enrolled_at' => now(),
        ]);

        $this->assertFalse($user->hasPaidMembership());
        $this->actingAs($user)->get(route('chat.course', $course))->assertForbidden();
    }

    public function test_personlig_traening_counts_as_a_membership(): void
    {
        $course = $this->course('Fælles', Course::TYPE_FAELLES);
        $user = User::factory()->create(['role' => 'user']);
        Enrollment::create([
            'user_id' => $user->id,
            'course_id' => $this->course('PT-forløb', Course::TYPE_PERSONLIG)->id,
            'status' => 'active',
            'enrolled_at' => now(),
        ]);

        $this->assertTrue($user->hasPaidMembership());
        $this->actingAs($user)->get(route('chat.course', $course))->assertOk();
    }

    public function test_chat_can_be_turned_off_per_faellestraening(): void
    {
        $course = $this->course('Stille fælles', Course::TYPE_FAELLES, ['chat_enabled' => false]);
        $member = $this->payingMember();

        $this->actingAs($member)->get(route('chat.course', $course))->assertNotFound();
        $this->actingAs($member)->get(route('courses.show', $course))
            ->assertOk()
            ->assertDontSee(route('chat.course', $course));
    }

    /* -------------------------------------------------------------- admin -- */

    public function test_courses_default_to_the_hold_type(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($owner)->post(route('admin.courses.store'), [
            'title' => 'Uden type',
            'description' => 'x',
            'trainer_ids' => [$owner->id],
            'price_kr' => 0,
            'max_participants' => 10,
        ])->assertSessionHasNoErrors();

        $this->assertSame(Course::TYPE_HOLD, Course::where('title', 'Uden type')->firstOrFail()->type);
    }

    public function test_owner_can_create_a_faellestraening_without_price_or_capacity(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($owner)->post(route('admin.courses.store'), [
            'title' => 'Ny fællestræning',
            'type' => Course::TYPE_FAELLES,
            'description' => 'x',
            'trainer_ids' => [$owner->id],
            'chat_enabled' => '1',
        ])->assertSessionHasNoErrors();

        $course = Course::where('title', 'Ny fællestræning')->firstOrFail();
        $this->assertTrue($course->isFaellestraening());
        $this->assertSame(0, $course->price_cents);
        $this->assertTrue($course->chat_enabled);
    }

    public function test_a_faellestraening_never_keeps_a_price(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($owner)->post(route('admin.courses.store'), [
            'title' => 'Gratis alligevel',
            'type' => Course::TYPE_FAELLES,
            'description' => 'x',
            'trainer_ids' => [$owner->id],
            'price_kr' => 250,
            'max_participants' => 10,
        ])->assertSessionHasNoErrors();

        $this->assertSame(0, Course::where('title', 'Gratis alligevel')->firstOrFail()->price_cents);
    }

    public function test_a_hold_still_requires_a_price_and_capacity(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($owner)->post(route('admin.courses.store'), [
            'title' => 'Uden pris',
            'type' => Course::TYPE_HOLD,
            'description' => 'x',
            'trainer_ids' => [$owner->id],
        ])->assertSessionHasErrors(['price_kr', 'max_participants']);
    }

    public function test_owner_can_create_personlig_traening(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($owner)->post(route('admin.courses.store'), [
            'title' => 'PT hos Anders',
            'type' => Course::TYPE_PERSONLIG,
            'description' => 'x',
            'trainer_ids' => [$owner->id],
            'price_kr' => 900,
            'max_participants' => 1,
        ])->assertSessionHasNoErrors();

        $course = Course::where('title', 'PT hos Anders')->firstOrFail();
        $this->assertTrue($course->isPersonlig());
        $this->assertSame(90000, $course->price_cents);
    }

    public function test_an_unknown_type_is_rejected(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($owner)->post(route('admin.courses.store'), [
            'title' => 'Sludder',
            'type' => 'yoga-retreat',
            'description' => 'x',
            'trainer_ids' => [$owner->id],
            'price_kr' => 0,
            'max_participants' => 10,
        ])->assertSessionHasErrors('type');
    }
}
