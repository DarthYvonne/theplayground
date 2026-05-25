<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class UserAdminController extends Controller
{
    public function index() {
        $users = User::orderBy('role')->orderBy('name')->get();
        return view('admin.users.index', compact('users'));
    }

    public function edit(User $user) {
        return view('admin.users.edit', compact('user'));
    }

    public function update(Request $request, User $user): RedirectResponse {
        $data = $request->validate([
            'name' => ['required','string','max:120'],
            'email' => ['required','email','unique:users,email,' . $user->id],
            'phone' => ['nullable','string','max:40'],
            'about' => ['nullable','string','max:2000'],
            'picture' => ['nullable','image','max:16384'],
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

        return redirect()->route('admin.users.edit', $user)->with('status', $user->name . ' er opdateret.');
    }

    public function updateRole(Request $request, User $user): RedirectResponse {
        $data = $request->validate(['role' => ['required', 'in:user,assistant,trainer,owner']]);
        // Refuse to demote the last owner
        if ($user->role === 'owner' && $data['role'] !== 'owner' && User::where('role','owner')->count() <= 1) {
            return back()->withErrors(['role' => 'Kan ikke nedgradere den sidste ejer.']);
        }
        $user->update(['role' => $data['role']]);
        $label = ['user' => 'bruger', 'assistant' => 'assistent', 'trainer' => 'træner', 'owner' => 'ejer'][$data['role']] ?? $data['role'];
        return back()->with('status', $user->name . ' er nu ' . $label . '.');
    }

    public function destroy(Request $request, User $user): RedirectResponse {
        if ($user->id === $request->user()->id) {
            return back()->withErrors(['delete' => 'Du kan ikke slette dig selv.']);
        }
        if ($user->role === 'owner' && User::where('role', 'owner')->count() <= 1) {
            return back()->withErrors(['delete' => 'Kan ikke slette den sidste ejer.']);
        }
        if ($user->trainerCourses()->exists()) {
            return back()->withErrors(['delete' => $user->name . ' underviser stadig på et eller flere hold. Flyt holdene til en anden træner først.']);
        }
        $name = $user->name;
        $user->delete();
        return redirect()->route('admin.users.index')->with('status', $name . ' er slettet.');
    }
}
