<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Support\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Personlig træning is a private 1:1 arrangement that people pay for, so these
 * cover the things that fail silently and cost something: who can see it, who
 * can pay for it, when the chat opens, and the claim that hands it to a member.
 * Presentation is deliberately not tested.
 */
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

    private function enroll(User $user, Course $course): Enrollment
    {
        return Enrollment::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'status' => 'active',
            'enrolled_at' => now(),
        ]);
    }

    public function test_it_is_invisible_to_everyone_it_does_not_belong_to(): void
    {
        $trainer = User::factory()->create(['role' => 'trainer', 'name' => 'Anders']);
        $member = User::factory()->create(['role' => 'user', 'name' => 'Zenobia']);
        $stranger = User::factory()->create(['role' => 'user']);
        $course = $this->pt($trainer, ['member_id' => $member->id]);
        $this->enroll($member, $course);
        $title = $course->title;

        // 404 rather than 403: a stranger should not be able to confirm it exists.
        $this->actingAs($stranger)->get(route('courses.show', $course))->assertNotFound();
        $this->get(route('courses.show', $course))->assertNotFound();
        // The three listings that span every course type.
        $this->actingAs($stranger)->get(route('personlig.index'))->assertOk()->assertDontSee($title);
        $this->actingAs($stranger)->get(route('home.calendar'))->assertOk()->assertDontSee($title);
        $this->actingAs($stranger)->get(route('members.show', $member))->assertOk()->assertDontSee($title);
        // A 1:1 has no roster page at all, not even for an owner.
        $this->actingAs(User::factory()->create(['role' => 'owner']))
            ->get(route('courses.members', $course))->assertNotFound();

        // The two people in it do see it.
        $this->actingAs($member)->get(route('courses.show', $course))->assertOk();
        $this->actingAs($member)->get(route('personlig.index'))->assertOk()->assertSee($title);
        $this->actingAs($trainer)->get(route('personlig.index'))->assertOk()->assertSee($title);
    }

    public function test_only_the_named_member_can_pay_for_it(): void
    {
        $member = User::factory()->create(['role' => 'user']);
        $stranger = User::factory()->create(['role' => 'user']);
        $course = $this->pt(null, ['member_id' => $member->id, 'free_enrollment' => true]);

        $this->actingAs($stranger)->post(route('enroll', $course))->assertForbidden();
        $this->actingAs($stranger)->post(route('enroll.card', $course))->assertForbidden();
        $this->assertSame(0, Enrollment::where('course_id', $course->id)->count());

        // Nobody at all may buy one that has not been assigned yet.
        $unassigned = $this->pt(null, ['member_invite_email' => 'ny@example.dk', 'free_enrollment' => true]);
        $this->actingAs($member)->post(route('enroll', $unassigned))->assertNotFound();

        $this->actingAs($member)->post(route('enroll', $course));
        $this->assertTrue($member->fresh()->enrolledIn($course));
    }

    public function test_an_invite_is_claimed_by_email_or_phone_and_only_once(): void
    {
        $trainer = User::factory()->create(['role' => 'trainer', 'name' => 'Anders']);
        $byEmail = $this->pt($trainer, ['member_invite_email' => 'mette@example.dk']);
        $byPhone = $this->pt($trainer, ['member_invite_phone' => Contact::normalizePhone('+45 12 34 56 78')]);
        $taken = $this->pt($trainer, [
            'member_id' => User::factory()->create(['role' => 'user'])->id,
            'member_invite_email' => 'mette@example.dk',
        ]);
        $alreadyMine = $taken->member_id;

        // Mixed case and a differently written phone still match.
        $this->post(route('register'), [
            'name' => 'Mette',
            'email' => 'Mette@Example.DK',
            'phone' => '0045 12345678',
            'password' => 'hemmeligt123',
            'password_confirmation' => 'hemmeligt123',
        ])->assertSessionHasNoErrors();

        $mette = User::where('email', 'Mette@Example.DK')->firstOrFail();
        $this->assertSame($mette->id, $byEmail->fresh()->member_id);
        $this->assertSame($mette->id, $byPhone->fresh()->member_id);
        $this->assertSame('Personlig træning — Anders & Mette', $byEmail->fresh()->title);
        // An already-claimed one is never taken from its owner.
        $this->assertSame($alreadyMine, $taken->fresh()->member_id);
        // The trainer is told, which is the only way a wrong claim surfaces.
        $this->assertSame(2, AppNotification::where('user_id', $trainer->id)->count());
    }

    public function test_someone_who_already_had_an_account_claims_theirs_at_login(): void
    {
        $user = User::factory()->create([
            'role' => 'user', 'email' => 'mette@example.dk', 'password' => bcrypt('hemmeligt123'),
        ]);
        $course = $this->pt(null, ['member_invite_email' => 'mette@example.dk']);

        $this->post(route('login'), ['email' => 'mette@example.dk', 'password' => 'hemmeligt123']);

        $this->assertSame($user->id, $course->fresh()->member_id);
    }

    public function test_a_trainer_creates_and_maintains_only_their_own(): void
    {
        $trainer = User::factory()->create(['role' => 'trainer', 'name' => 'Anders']);
        $member = User::factory()->create(['role' => 'user', 'name' => 'Mette']);

        // No title, description or capacity is asked for — or accepted.
        $created = $this->actingAs($trainer)->post(route('admin.courses.store'), [
            'type' => Course::TYPE_PERSONLIG,
            'trainer_ids' => [$trainer->id],
            'member_invite_email' => 'typo@example.dk',
            'price_kr' => 900,
        ])->assertSessionHasNoErrors();

        $course = Course::personlig()->firstOrFail();
        $this->assertSame('Personlig træning — Anders & typo@example.dk', $course->title);
        $this->assertSame(90000, $course->price_cents);
        // They must not be 403'd out of the thing they just made.
        $this->actingAs($trainer)->get($created->headers->get('Location'))->assertOk();

        // The point of letting a trainer edit: fixing their own mistyped invite.
        // A submitted type is a stray field and must not convert the training.
        $this->actingAs($trainer)->post(route('admin.courses.update', $course), [
            'type' => Course::TYPE_HOLD,
            'trainer_ids' => [$trainer->id],
            'member_id' => $member->id,
            'price_kr' => 900,
        ])->assertSessionHasNoErrors();

        $course->refresh();
        $this->assertTrue($course->isPersonlig());
        $this->assertSame($member->id, $course->member_id);

        // Somebody else's training, and deleting, stay out of reach.
        $other = $this->pt(User::factory()->create(['role' => 'trainer']));
        $this->actingAs($trainer)->get(route('admin.courses.edit', $other))->assertForbidden();
        $this->actingAs($trainer)->post(route('admin.courses.update', $other), [])->assertForbidden();
        $this->actingAs($trainer)->post(route('admin.courses.destroy', $course))->assertForbidden();
    }

    public function test_it_cannot_be_created_without_exactly_one_trainer_and_one_member(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $second = User::factory()->create(['role' => 'trainer']);
        $member = User::factory()->create(['role' => 'user']);
        $base = ['type' => Course::TYPE_PERSONLIG, 'price_kr' => 900];

        $this->actingAs($owner)->post(route('admin.courses.store'), $base + ['trainer_ids' => [$owner->id]])
            ->assertSessionHasErrors('member_id');
        $this->actingAs($owner)->post(route('admin.courses.store'), $base + ['trainer_ids' => [$owner->id, $second->id], 'member_id' => $member->id])
            ->assertSessionHasErrors('trainer_ids');
        $this->actingAs($owner)->post(route('admin.courses.store'), $base + ['trainer_ids' => [$owner->id], 'member_id' => $owner->id])
            ->assertSessionHasErrors('member_id');

        $this->assertSame(0, Course::personlig()->count());
    }

    public function test_an_invited_email_that_already_has_an_account_links_at_once(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $existing = User::factory()->create(['role' => 'user', 'email' => 'mette@example.dk']);

        // Never leave an existing member pending, where a claim could race.
        $this->actingAs($owner)->post(route('admin.courses.store'), [
            'type' => Course::TYPE_PERSONLIG,
            'trainer_ids' => [$owner->id],
            'member_invite_email' => 'METTE@example.dk',
            'price_kr' => 900,
        ])->assertSessionHasNoErrors();

        $this->assertSame($existing->id, Course::personlig()->firstOrFail()->member_id);
    }

    public function test_its_card_is_the_trainers_photo_and_it_has_no_capacity(): void
    {
        $trainer = User::factory()->create(['role' => 'trainer', 'picture_path' => 'avatars/anders.jpg']);
        $member = User::factory()->create(['role' => 'user']);
        // A stale image_path from another type must not win.
        $course = $this->pt($trainer, ['member_id' => $member->id, 'image_path' => 'courses/stale.jpg', 'chat_enabled' => false]);
        $this->enroll($member, $course);

        $this->assertStringContainsString('anders.jpg', $course->heroImageUrl());
        $this->assertStringNotContainsString('stale.jpg', $course->heroImageUrl());
        $this->assertFalse($course->isFull());
        $this->assertFalse($course->hasMemberList());
        $this->assertTrue($course->hasChat(), 'chat_enabled belongs to fællestræning; a 1:1 always has one');
    }
}
