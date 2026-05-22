<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
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
        if (!Auth::attempt($data, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'E-mail eller adgangskode passer ikke.'])->onlyInput('email');
        }
        $request->session()->regenerate();
        return redirect()->intended('/dashboard');
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
        return redirect('/dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/');
    }
}
