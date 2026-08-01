<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function showLogin() { return view('auth.login'); }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required','email'],
            'password' => ['required','string'],
        ]);

        // Keyed on e-mail + IP rather than IP alone, so one attacker on a shared
        // network (gym wifi, office NAT) can't lock everyone else out.
        $throttleKey = Str::lower($data['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            $minutes = (int) ceil($seconds / 60);

            return back()->withErrors(['email' => $seconds > 60
                ? "For mange loginforsøg. Prøv igen om $minutes minutter."
                : "For mange loginforsøg. Prøv igen om $seconds sekunder.",
            ])->onlyInput('email');
        }

        if (!Auth::attempt($data, $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey, 900);

            return back()->withErrors(['email' => 'E-mail eller adgangskode passer ikke.'])->onlyInput('email');
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();
        return redirect()->intended('/feed');
    }

    public function showRegister() { return view('auth.register'); }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required','string','max:120'],
            'email' => ['required','email','unique:users,email'],
            'password' => ['required','confirmed', Password::min(8)],
            'phone' => ['nullable','string','max:40'],
        ]);
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'phone' => $data['phone'] ?? null,
            'role' => 'user',
        ]);
        Auth::login($user);
        $request->session()->regenerate();
        return redirect()->intended('/feed');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/');
    }
}
