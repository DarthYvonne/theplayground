<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function edit(Request $request) {
        return view('profile.edit', ['user' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required','string','max:120'],
            'email' => ['required','email','unique:users,email,' . $user->id],
            'phone' => ['nullable','string','max:40'],
            'about' => ['nullable','string','max:2000'],
            'picture' => ['nullable','image','max:4096'],
            'password' => ['nullable','confirmed','min:8'],
        ]);

        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->phone = $data['phone'] ?? null;
        $user->about = $data['about'] ?? null;
        if ($request->hasFile('picture')) {
            if ($user->picture_path) Storage::disk('public')->delete($user->picture_path);
            $user->picture_path = $request->file('picture')->store('avatars', 'public');
        }
        if (!empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }
        $user->save();

        return back()->with('status', 'Profile updated.');
    }
}
