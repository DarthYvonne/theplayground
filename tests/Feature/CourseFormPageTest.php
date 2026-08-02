<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseFormPageTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->create(['role' => 'owner']);
    }

    public function test_create_page_renders_every_card(): void
    {
        // All of them are in the markup; the type selector shows and hides them.
        $this->actingAs($this->owner())->get(route('admin.courses.create'))
            ->assertOk()
            ->assertSee('Grundlæggende')
            ->assertSee('Trænere')
            ->assertSee('Medlem')
            ->assertSee('Træningstider')
            ->assertSee('Pris &amp; tilmelding', false)
            ->assertSee('Fællestræning')
            ->assertSee('Forsidemedie');
    }

    public function test_the_personlig_form_opens_with_the_member_picker(): void
    {
        $this->actingAs($this->owner())->get(route('admin.courses.create', ['type' => \App\Models\Course::TYPE_PERSONLIG]))
            ->assertOk()
            ->assertSee('Ny personlig træning')
            ->assertSee('Find medlem')
            ->assertSee(route('admin.members.search'));
    }

    public function test_trainer_rows_get_contact_details(): void
    {
        $owner = $this->owner();
        $trainer = User::factory()->create([
            'role' => 'trainer',
            'name' => 'Mette Træner',
            'email' => 'mette@example.dk',
            'phone' => '12345678',
        ]);

        $course = Course::create([
            'title' => 'Hold A',
            'description' => 'x',
            'price_cents' => 0,
            'is_active' => true,
            'max_participants' => 10,
        ]);
        $course->trainers()->sync([$trainer->id, $owner->id]);

        $html = $this->actingAs($owner)->get(route('admin.courses.edit', $course))->assertOk()->getContent();

        // The rows are built client-side from this payload.
        $this->assertStringContainsString('mette@example.dk', $html);
        $this->assertStringContainsString('12345678', $html);
        $this->assertStringContainsString('Mette Tr', $html);
    }

    public function test_delete_button_targets_the_destroy_form_not_the_update_form(): void
    {
        $owner = $this->owner();
        $course = Course::create([
            'title' => 'Hold A',
            'description' => 'x',
            'price_cents' => 0,
            'is_active' => true,
            'max_participants' => 10,
        ]);
        $course->trainers()->sync([$owner->id]);

        $html = $this->actingAs($owner)->get(route('admin.courses.edit', $course))->assertOk()->getContent();

        // The destroy form must sit outside the main form, or the parser drops it
        // and "Slet hold" submits an update instead.
        $this->assertStringContainsString('form="delete-course-form"', $html);
        $this->assertStringContainsString('id="delete-course-form"', $html);
        $mainFormEnd = strpos($html, '</form>');
        $this->assertGreaterThan($mainFormEnd, strpos($html, 'id="delete-course-form"'));
    }

    public function test_active_toggle_is_locked_while_the_hold_has_members(): void
    {
        $owner = $this->owner();
        $course = Course::create([
            'title' => 'Fuldt hold',
            'description' => 'x',
            'price_cents' => 15000,
            'is_active' => true,
            'max_participants' => 10,
        ]);
        $course->trainers()->sync([$owner->id]);
        \App\Models\Enrollment::create([
            'user_id' => User::factory()->create(['role' => 'user'])->id,
            'course_id' => $course->id,
            'status' => 'active',
            'enrolled_at' => now(),
        ]);

        $this->actingAs($owner)->get(route('admin.courses.edit', $course))
            ->assertOk()
            ->assertSee('kan ikke gøres inaktivt')
            ->assertSee('disabled', false);

        // And the guard holds even if the disabled attribute is bypassed.
        $this->actingAs($owner)->post(route('admin.courses.update', $course), [
            'title' => 'Fuldt hold',
            'description' => 'x',
            'trainer_ids' => [$owner->id],
            'price_kr' => 150,
            'max_participants' => 10,
            // is_active omitted entirely — the off state.
        ])->assertSessionHasErrors('is_active');

        $this->assertTrue($course->fresh()->is_active);
    }

    public function test_a_hold_without_members_can_still_be_deactivated(): void
    {
        $owner = $this->owner();
        $course = Course::create([
            'title' => 'Tomt hold',
            'description' => 'x',
            'price_cents' => 0,
            'is_active' => true,
            'max_participants' => 10,
        ]);
        $course->trainers()->sync([$owner->id]);

        $this->actingAs($owner)->post(route('admin.courses.update', $course), [
            'title' => 'Tomt hold',
            'description' => 'x',
            'trainer_ids' => [$owner->id],
            'price_kr' => 0,
            'max_participants' => 10,
        ])->assertSessionHasNoErrors();

        $this->assertFalse($course->fresh()->is_active);
    }

    public function test_delete_still_works(): void
    {
        $owner = $this->owner();
        $course = Course::create([
            'title' => 'Slet mig',
            'description' => 'x',
            'price_cents' => 0,
            'is_active' => true,
            'max_participants' => 10,
        ]);

        $this->actingAs($owner)->post(route('admin.courses.destroy', $course))->assertSessionHasNoErrors();

        $this->assertNull(Course::find($course->id));
    }
}
