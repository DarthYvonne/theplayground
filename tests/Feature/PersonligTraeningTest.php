<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Support\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonligTraeningTest extends TestCase
{
    use RefreshDatabase;

    private function pt(?User $trainer = null, array $attrs = []): Course
    {
        $trainer ??= User::factory()->create(['role' => 'trainer', 'name' => 'Anders']);

        $course = Course::create(array_merge([
            'title' => 'Personlig træning',
            'type' => Course::TYPE_PERSONLIG,
            'description' => '',
            'price_cents' => 90000,
            'is_active' => true,
            'max_participants' => 1,
        ], $attrs));
        $course->trainers()->sync([$trainer->id]);
        $course->schedules()->create(['weekday' => 'mon', 'start_time' => '17:00', 'end_time' => '18:00']);
        $course->refresh()->refreshPersonligTitle();

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

    /* --------------------------------------------------------- title/model -- */

    public function test_the_title_is_generated_from_the_two_people(): void
    {
        $trainer = User::factory()->create(['role' => 'trainer', 'name' => 'Anders']);
        $member = User::factory()->create(['role' => 'user', 'name' => 'Mette']);

        $course = $this->pt($trainer, ['member_id' => $member->id]);

        $this->assertSame('Personlig træning — Anders & Mette', $course->title);
    }

    public function test_an_unclaimed_training_is_titled_after_the_invite(): void
    {
        $course = $this->pt(null, ['member_invite_email' => 'mette@example.dk']);

        $this->assertSame('Personlig træning — Anders & mette@example.dk', $course->title);
    }

    public function test_swapping_the_trainer_retitles_it(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $trainer = User::factory()->create(['role' => 'trainer', 'name' => 'Anders']);
        $replacement = User::factory()->create(['role' => 'trainer', 'name' => 'Bo']);
        $member = User::factory()->create(['role' => 'user', 'name' => 'Mette']);
        $course = $this->pt($trainer, ['member_id' => $member->id]);

        $this->actingAs($owner)->post(route('admin.users.swapTrainer', [$trainer, $course]), [
            'trainer_id' => $replacement->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame('Personlig træning — Bo & Mette', $course->fresh()->title);
    }

    public function test_the_card_image_is_the_trainers_photo(): void
    {
        $trainer = User::factory()->create(['role' => 'trainer', 'picture_path' => 'avatars/anders.jpg']);
        // An image_path left over from another type must not win.
        $course = $this->pt($trainer, ['image_path' => 'courses/stale.jpg']);

        $this->assertStringContainsString('anders.jpg', $course->heroImageUrl());
        $this->assertStringNotContainsString('stale.jpg', $course->heroImageUrl());
    }

    public function test_it_has_no_roster_and_no_capacity_and_always_has_chat(): void
    {
        $member = User::factory()->create(['role' => 'user']);
        $course = $this->pt(null, ['member_id' => $member->id, 'chat_enabled' => false]);
        $this->enroll($member, $course);

        $this->assertFalse($course->hasMemberList());
        $this->assertTrue($course->hasChat());
        $this->assertFalse($course->isFull());
    }

    /* --------------------------------------------------------------- admin -- */

    public function test_a_trainer_can_create_one_and_is_not_403d_afterwards(): void
    {
        $trainer = User::factory()->create(['role' => 'trainer', 'name' => 'Anders']);
        $member = User::factory()->create(['role' => 'user', 'name' => 'Mette']);

        $this->actingAs($trainer)->get(route('admin.courses.create', ['type' => Course::TYPE_PERSONLIG]))->assertOk();

        $response = $this->actingAs($trainer)->post(route('admin.courses.store'), [
            'type' => Course::TYPE_PERSONLIG,
            'trainer_ids' => [$trainer->id],
            'member_id' => $member->id,
            'price_kr' => 900,
        ])->assertSessionHasNoErrors();

        // A trainer may not edit, so the post-create redirect must not go there.
        $this->actingAs($trainer)->get($response->headers->get('Location'))->assertOk();
    }

    public function test_a_trainer_still_cannot_edit_or_delete(): void
    {
        $trainer = User::factory()->create(['role' => 'trainer']);
        $course = $this->pt($trainer);

        $this->actingAs($trainer)->get(route('admin.courses.index'))->assertForbidden();
        $this->actingAs($trainer)->get(route('admin.courses.edit', $course))->assertForbidden();
        $this->actingAs($trainer)->post(route('admin.courses.update', $course), [])->assertForbidden();
        $this->actingAs($trainer)->post(route('admin.courses.destroy', $course))->assertForbidden();
    }

    public function test_it_needs_a_member_or_an_invite(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($owner)->post(route('admin.courses.store'), [
            'type' => Course::TYPE_PERSONLIG,
            'trainer_ids' => [$owner->id],
            'price_kr' => 900,
        ])->assertSessionHasErrors('member_id');

        $this->assertSame(0, Course::personlig()->count());
    }

    public function test_it_takes_exactly_one_trainer(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $other = User::factory()->create(['role' => 'trainer']);
        $member = User::factory()->create(['role' => 'user']);

        $this->actingAs($owner)->post(route('admin.courses.store'), [
            'type' => Course::TYPE_PERSONLIG,
            'trainer_ids' => [$owner->id, $other->id],
            'member_id' => $member->id,
            'price_kr' => 900,
        ])->assertSessionHasErrors('trainer_ids');
    }

    public function test_the_trainer_cannot_also_be_the_member(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($owner)->post(route('admin.courses.store'), [
            'type' => Course::TYPE_PERSONLIG,
            'trainer_ids' => [$owner->id],
            'member_id' => $owner->id,
            'price_kr' => 900,
        ])->assertSessionHasErrors('member_id');
    }

    public function test_an_invited_email_that_already_exists_links_immediately(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $existing = User::factory()->create(['role' => 'user', 'email' => 'mette@example.dk']);

        $this->actingAs($owner)->post(route('admin.courses.store'), [
            'type' => Course::TYPE_PERSONLIG,
            'trainer_ids' => [$owner->id],
            'member_invite_email' => 'METTE@example.dk',
            'price_kr' => 900,
        ])->assertSessionHasNoErrors();

        $this->assertSame($existing->id, Course::personlig()->firstOrFail()->member_id);
    }

    public function test_retyping_it_to_a_hold_clears_the_member(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $member = User::factory()->create(['role' => 'user']);
        $course = $this->pt($owner, ['member_id' => $member->id]);

        $this->actingAs($owner)->post(route('admin.courses.update', $course), [
            'title' => 'Nu et hold',
            'type' => Course::TYPE_HOLD,
            'description' => 'x',
            'trainer_ids' => [$owner->id],
            'price_kr' => 300,
            'max_participants' => 12,
        ])->assertSessionHasNoErrors();

        $this->assertNull($course->fresh()->member_id);
    }

    /* --------------------------------------------------------------- claim -- */

    public function test_registering_with_the_invited_email_claims_it(): void
    {
        $course = $this->pt(null, ['member_invite_email' => 'mette@example.dk']);

        $this->post(route('register'), [
            'name' => 'Mette',
            'email' => 'Mette@Example.DK',
            'password' => 'hemmeligt123',
            'password_confirmation' => 'hemmeligt123',
        ])->assertSessionHasNoErrors();

        $course->refresh();
        $member = User::where('email', 'Mette@Example.DK')->firstOrFail();
        $this->assertSame($member->id, $course->member_id);
        $this->assertNotNull($course->member_claimed_at);
        $this->assertSame('Personlig træning — Anders & Mette', $course->title);
    }

    public function test_the_trainer_is_notified_when_someone_claims(): void
    {
        $trainer = User::factory()->create(['role' => 'trainer', 'name' => 'Anders']);
        $this->pt($trainer, ['member_invite_email' => 'mette@example.dk']);

        $this->post(route('register'), [
            'name' => 'Mette',
            'email' => 'mette@example.dk',
            'password' => 'hemmeligt123',
            'password_confirmation' => 'hemmeligt123',
        ]);

        $this->assertSame(1, AppNotification::where('user_id', $trainer->id)->count());
    }

    public function test_a_different_email_does_not_claim_it(): void
    {
        $course = $this->pt(null, ['member_invite_email' => 'mette@example.dk']);

        $this->post(route('register'), [
            'name' => 'Ikke Mette',
            'email' => 'anden@example.dk',
            'password' => 'hemmeligt123',
            'password_confirmation' => 'hemmeligt123',
        ]);

        $this->assertNull($course->fresh()->member_id);
    }

    public function test_a_phone_invite_is_claimed_however_it_was_written(): void
    {
        $course = $this->pt(null, ['member_invite_phone' => Contact::normalizePhone('+45 12 34 56 78')]);

        $this->post(route('register'), [
            'name' => 'Mette',
            'email' => 'mette@example.dk',
            'phone' => '0045 12345678',
            'password' => 'hemmeligt123',
            'password_confirmation' => 'hemmeligt123',
        ]);

        $this->assertNotNull($course->fresh()->member_id);
    }

    public function test_an_already_claimed_training_is_never_reclaimed(): void
    {
        $first = User::factory()->create(['role' => 'user', 'name' => 'Mette']);
        $course = $this->pt(null, ['member_id' => $first->id, 'member_invite_email' => 'delt@example.dk']);

        $this->post(route('register'), [
            'name' => 'Tyv',
            'email' => 'delt@example.dk',
            'password' => 'hemmeligt123',
            'password_confirmation' => 'hemmeligt123',
        ]);

        $this->assertSame($first->id, $course->fresh()->member_id);
    }

    public function test_logging_in_claims_a_pending_invite(): void
    {
        $user = User::factory()->create(['role' => 'user', 'email' => 'mette@example.dk', 'password' => bcrypt('hemmeligt123')]);
        $course = $this->pt(null, ['member_invite_email' => 'mette@example.dk']);

        $this->post(route('login'), ['email' => 'mette@example.dk', 'password' => 'hemmeligt123']);

        $this->assertSame($user->id, $course->fresh()->member_id);
    }

    public function test_phone_normalisation_collapses_the_common_forms(): void
    {
        $this->assertSame('12345678', Contact::normalizePhone('+45 12 34 56 78'));
        $this->assertSame('12345678', Contact::normalizePhone('0045 12345678'));
        $this->assertSame('12345678', Contact::normalizePhone('12345678'));
        $this->assertNull(Contact::normalizePhone(''));
    }

    /* ---------------------------------------------------------- enrollment -- */

    public function test_only_the_named_member_can_enroll(): void
    {
        $member = User::factory()->create(['role' => 'user']);
        $stranger = User::factory()->create(['role' => 'user']);
        $course = $this->pt(null, ['member_id' => $member->id, 'free_enrollment' => true]);

        $this->actingAs($stranger)->post(route('enroll', $course))->assertForbidden();
        $this->assertSame(0, Enrollment::where('course_id', $course->id)->count());

        $this->actingAs($member)->post(route('enroll', $course));
        $this->assertTrue($member->fresh()->enrolledIn($course));
    }

    public function test_an_unclaimed_training_cannot_be_bought_by_anyone(): void
    {
        $someone = User::factory()->create(['role' => 'user']);
        $course = $this->pt(null, ['member_invite_email' => 'mette@example.dk']);

        $this->actingAs($someone)->post(route('enroll', $course))->assertNotFound();
        $this->actingAs($someone)->post(route('enroll.card', $course))->assertNotFound();
    }

    /* -------------------------------------------------------------- access -- */

    public function test_chat_and_media_stay_locked_until_the_member_has_paid(): void
    {
        $member = User::factory()->create(['role' => 'user']);
        $course = $this->pt(null, ['member_id' => $member->id]);

        $this->actingAs($member)->get(route('chat.course', $course))->assertForbidden();
        $this->actingAs($member)->get(route('courses.media', $course))->assertForbidden();

        $this->enroll($member, $course);

        $this->actingAs($member)->get(route('chat.course', $course))->assertOk();
    }

    public function test_the_trainer_reaches_the_chat_before_the_member_pays(): void
    {
        $trainer = User::factory()->create(['role' => 'trainer']);
        $member = User::factory()->create(['role' => 'user']);
        $course = $this->pt($trainer, ['member_id' => $member->id]);

        $this->actingAs($trainer)->get(route('chat.course', $course))->assertOk();
    }

    public function test_a_stranger_cannot_confirm_it_exists(): void
    {
        $member = User::factory()->create(['role' => 'user']);
        $stranger = User::factory()->create(['role' => 'user']);
        $course = $this->pt(null, ['member_id' => $member->id]);

        // 404, not 403 — a private arrangement should not be confirmable.
        $this->actingAs($stranger)->get(route('courses.show', $course))->assertNotFound();
        $this->get(route('courses.show', $course))->assertNotFound();
    }

    public function test_it_has_no_member_page(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $course = $this->pt();

        $this->actingAs($owner)->get(route('courses.members', $course))->assertNotFound();
    }

    /* ------------------------------------------------------------- listing -- */

    public function test_the_listing_shows_only_what_you_are_part_of(): void
    {
        $trainer = User::factory()->create(['role' => 'trainer', 'name' => 'Anders']);
        $mine = User::factory()->create(['role' => 'user', 'name' => 'Mette']);
        $other = User::factory()->create(['role' => 'user', 'name' => 'Ida']);

        $this->pt($trainer, ['member_id' => $mine->id]);
        $this->pt(User::factory()->create(['role' => 'trainer', 'name' => 'Bo']), ['member_id' => $other->id]);

        $this->actingAs($mine)->get(route('personlig.index'))
            ->assertOk()->assertSee('Mette')->assertDontSee('Ida');

        $this->actingAs($trainer)->get(route('personlig.index'))
            ->assertOk()->assertSee('Mette')->assertDontSee('Ida');

        $this->actingAs(User::factory()->create(['role' => 'owner']))->get(route('personlig.index'))
            ->assertOk()->assertSee('Mette')->assertSee('Ida');
    }

    public function test_it_does_not_leak_onto_the_shared_calendar(): void
    {
        $member = User::factory()->create(['role' => 'user', 'name' => 'Mette']);
        $stranger = User::factory()->create(['role' => 'user']);
        $this->pt(null, ['member_id' => $member->id]);

        $this->actingAs($stranger)->get(route('home.calendar'))->assertOk()->assertDontSee('Mette');
        $this->actingAs($member)->get(route('home.calendar'))->assertOk()->assertSee('Mette');
    }

    public function test_it_does_not_leak_on_a_member_profile(): void
    {
        $member = User::factory()->create(['role' => 'user', 'name' => 'Mette']);
        $stranger = User::factory()->create(['role' => 'user']);
        $course = $this->pt(null, ['member_id' => $member->id]);
        $this->enroll($member, $course);

        $this->actingAs($stranger)->get(route('members.show', $member))
            ->assertOk()->assertDontSee('Personlig træning —');
    }
}
