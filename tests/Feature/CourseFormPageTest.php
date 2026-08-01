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

    public function test_create_page_renders_the_five_cards(): void
    {
        $this->actingAs($this->owner())->get(route('admin.courses.create'))
            ->assertOk()
            ->assertSee('Grundlæggende')
            ->assertSee('Trænere')
            ->assertSee('Træningstider')
            ->assertSee('Pris &amp; tilmelding', false)
            ->assertSee('Forsidemedie');
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
