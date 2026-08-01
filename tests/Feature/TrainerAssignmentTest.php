<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrainerAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->create(['role' => 'owner']);
    }

    private function course(string $title = 'Hold A'): Course
    {
        return Course::create([
            'title' => $title,
            'description' => 'x',
            'price_cents' => 0,
            'free_enrollment' => true,
            'is_active' => true,
            'max_participants' => 10,
        ]);
    }

    public function test_demotion_is_blocked_while_the_user_still_trains_a_course(): void
    {
        $owner = $this->owner();
        $trainer = User::factory()->create(['role' => 'trainer']);
        $course = $this->course('Yoga');
        $course->trainers()->sync([$trainer->id]);

        $this->actingAs($owner)
            ->post(route('admin.users.role', $trainer), ['role' => 'user'])
            ->assertSessionHasErrors('role');

        $this->assertSame('trainer', $trainer->fresh()->role);
        $this->assertTrue($course->fresh()->hasTrainer($trainer));
    }

    public function test_demotion_succeeds_once_the_courses_are_handed_over(): void
    {
        $owner = $this->owner();
        $trainer = User::factory()->create(['role' => 'trainer']);
        $course = $this->course('Yoga');
        $course->trainers()->sync([$trainer->id]);

        $this->actingAs($owner)
            ->post(route('admin.users.swapTrainer', [$trainer, $course]), ['trainer_id' => $owner->id])
            ->assertSessionHasNoErrors();

        $course->refresh();
        $this->assertFalse($course->hasTrainer($trainer));
        $this->assertTrue($course->hasTrainer($owner));

        $this->actingAs($owner)
            ->post(route('admin.users.role', $trainer), ['role' => 'user'])
            ->assertSessionHasNoErrors();

        $this->assertSame('user', $trainer->fresh()->role);
    }

    public function test_swap_keeps_the_other_trainers_on_the_course(): void
    {
        $owner = $this->owner();
        $leaving = User::factory()->create(['role' => 'trainer']);
        $staying = User::factory()->create(['role' => 'trainer']);
        $incoming = User::factory()->create(['role' => 'trainer']);

        $course = $this->course('Styrke');
        $course->trainers()->sync([$leaving->id, $staying->id]);

        $this->actingAs($owner)
            ->post(route('admin.users.swapTrainer', [$leaving, $course]), ['trainer_id' => $incoming->id])
            ->assertSessionHasNoErrors();

        $ids = $course->fresh()->trainers->pluck('id')->all();
        $this->assertContains($staying->id, $ids);
        $this->assertContains($incoming->id, $ids);
        $this->assertNotContains($leaving->id, $ids);
    }

    public function test_swap_rejects_a_replacement_who_is_not_a_trainer(): void
    {
        $owner = $this->owner();
        $trainer = User::factory()->create(['role' => 'trainer']);
        $member = User::factory()->create(['role' => 'user']);
        $course = $this->course();
        $course->trainers()->sync([$trainer->id]);

        $this->actingAs($owner)
            ->post(route('admin.users.swapTrainer', [$trainer, $course]), ['trainer_id' => $member->id])
            ->assertSessionHasErrors('trainer_id');

        $this->assertTrue($course->fresh()->hasTrainer($trainer));
    }

    public function test_course_form_rejects_a_non_trainer_in_trainer_ids(): void
    {
        $owner = $this->owner();
        $member = User::factory()->create(['role' => 'user']);

        $this->actingAs($owner)->post(route('admin.courses.store'), [
            'title' => 'Nyt hold',
            'description' => 'x',
            'trainer_ids' => [$member->id],
            'price_kr' => 0,
            'max_participants' => 10,
        ])->assertSessionHasErrors('trainer_ids.0');

        $this->assertSame(0, Course::where('title', 'Nyt hold')->count());
    }

    public function test_owner_sees_the_courses_a_trainer_is_assigned_to(): void
    {
        $owner = $this->owner();
        $trainer = User::factory()->create(['role' => 'trainer']);
        $course = $this->course('Morgenhold');
        $course->trainers()->sync([$trainer->id]);

        $this->actingAs($owner)->get(route('admin.users.edit', $trainer))
            ->assertOk()
            ->assertSee('Underviser på')
            ->assertSee('Morgenhold')
            ->assertSee('Skift træner');
    }
}
