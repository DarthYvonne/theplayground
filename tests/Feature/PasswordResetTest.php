<?php

namespace Tests\Feature;

use App\Mail\ResetPasswordMail;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $overrides = []): User
    {
        return User::create(array_merge([
            'name' => 'Test Bruger',
            'email' => 'test@theplayground.dk',
            'password' => Hash::make('gammel-kode-123'),
            'role' => 'user',
        ], $overrides));
    }

    public function test_forgot_password_page_renders(): void
    {
        $this->get('/glemt-adgangskode')->assertOk()->assertSee('Glemt adgangskode');
    }

    public function test_login_page_links_to_password_recovery(): void
    {
        $this->get('/login')->assertOk()->assertSee(route('password.request'));
    }

    public function test_requesting_a_link_sends_the_reset_mail(): void
    {
        Mail::fake();
        $user = $this->user();

        $this->post('/glemt-adgangskode', ['email' => $user->email])
            ->assertRedirect()
            ->assertSessionHas('status');

        Mail::assertSent(ResetPasswordMail::class, fn ($mail) => $mail->hasTo($user->email));
    }

    public function test_unknown_email_reports_success_without_sending(): void
    {
        Mail::fake();

        // Same response as a real address — the form must not reveal who has an account.
        $this->post('/glemt-adgangskode', ['email' => 'findes-ikke@theplayground.dk'])
            ->assertRedirect()
            ->assertSessionHas('status')
            ->assertSessionHasNoErrors();

        Mail::assertNothingSent();
    }

    public function test_a_valid_token_resets_the_password(): void
    {
        Event::fake([PasswordReset::class]);
        $user = $this->user();
        $token = Password::broker()->createToken($user);

        $this->post('/nulstil-adgangskode', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'ny-hemmelig-kode',
            'password_confirmation' => 'ny-hemmelig-kode',
        ])->assertRedirect(route('login'))->assertSessionHas('status');

        $this->assertTrue(Hash::check('ny-hemmelig-kode', $user->fresh()->password));
        Event::assertDispatched(PasswordReset::class);
    }

    public function test_the_new_password_actually_works_at_login(): void
    {
        $user = $this->user();
        $token = Password::broker()->createToken($user);

        $this->post('/nulstil-adgangskode', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'ny-hemmelig-kode',
            'password_confirmation' => 'ny-hemmelig-kode',
        ]);

        $this->post('/login', ['email' => $user->email, 'password' => 'ny-hemmelig-kode'])
            ->assertRedirect('/feed');
        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_an_invalid_token_is_rejected(): void
    {
        $user = $this->user();

        $this->post('/nulstil-adgangskode', [
            'token' => 'noget-vrovl',
            'email' => $user->email,
            'password' => 'ny-hemmelig-kode',
            'password_confirmation' => 'ny-hemmelig-kode',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('gammel-kode-123', $user->fresh()->password));
    }

    public function test_a_token_cannot_be_reused(): void
    {
        $user = $this->user();
        $token = Password::broker()->createToken($user);
        $payload = [
            'token' => $token,
            'email' => $user->email,
            'password' => 'ny-hemmelig-kode',
            'password_confirmation' => 'ny-hemmelig-kode',
        ];

        $this->post('/nulstil-adgangskode', $payload)->assertSessionHasNoErrors();

        $this->post('/nulstil-adgangskode', array_merge($payload, [
            'password' => 'endnu-en-kode',
            'password_confirmation' => 'endnu-en-kode',
        ]))->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('ny-hemmelig-kode', $user->fresh()->password));
    }

    public function test_mismatched_confirmation_is_rejected(): void
    {
        $user = $this->user();
        $token = Password::broker()->createToken($user);

        $this->post('/nulstil-adgangskode', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'ny-hemmelig-kode',
            'password_confirmation' => 'noget-andet',
        ])->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('gammel-kode-123', $user->fresh()->password));
    }

    public function test_login_locks_out_after_five_failed_attempts(): void
    {
        $user = $this->user();
        RateLimiter::clear(strtolower($user->email).'|127.0.0.1');

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'forkert'])
                ->assertSessionHasErrors('email');
        }

        // Sixth attempt is refused even though the password is correct.
        $this->post('/login', ['email' => $user->email, 'password' => 'gammel-kode-123'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_successful_login_clears_the_throttle(): void
    {
        $user = $this->user();
        RateLimiter::clear(strtolower($user->email).'|127.0.0.1');

        $this->post('/login', ['email' => $user->email, 'password' => 'forkert']);
        $this->post('/login', ['email' => $user->email, 'password' => 'gammel-kode-123'])
            ->assertRedirect('/feed');

        $this->assertSame(0, RateLimiter::attempts(strtolower($user->email).'|127.0.0.1'));
    }
}
