<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    public function showForgot(): View { return view('auth.forgot-password'); }

    public function sendLink(Request $request): RedirectResponse
    {
        $request->validate(
            ['email' => ['required', 'email']],
            ['email.required' => 'Skriv din e-mail.', 'email.email' => 'Det ligner ikke en e-mail.']
        );

        $status = Password::sendResetLink($request->only('email'));

        if ($status === Password::RESET_THROTTLED) {
            return back()
                ->withErrors(['email' => 'Du har lige bedt om et link. Vent et minut, før du prøver igen.'])
                ->onlyInput('email');
        }

        // Deliberately the same answer whether or not the address exists — otherwise
        // this form becomes an oracle for which e-mails have accounts here.
        return back()->with('status', 'Hvis der findes en konto med den e-mail, har vi sendt et link til at nulstille adgangskoden.');
    }

    public function showReset(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ], [
            'password.required' => 'Vælg en ny adgangskode.',
            'password.confirmed' => 'De to adgangskoder er ikke ens.',
            'password.min' => 'Adgangskoden skal være mindst 8 tegn.',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, string $password) {
                // New remember_token invalidates "husk mig" cookies on other devices.
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')
                ->with('status', 'Din adgangskode er nulstillet. Log ind med den nye.');
        }

        return back()->withErrors(['email' => match ($status) {
            Password::INVALID_TOKEN => 'Linket er udløbet eller allerede brugt. Bed om et nyt herunder.',
            Password::INVALID_USER => 'Vi kunne ikke finde en konto med den e-mail.',
            Password::RESET_THROTTLED => 'For mange forsøg. Vent lidt, og prøv igen.',
            default => 'Adgangskoden kunne ikke nulstilles. Prøv igen.',
        }])->onlyInput('email');
    }
}
