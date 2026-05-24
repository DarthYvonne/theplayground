<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UserAdminController extends Controller
{
    public function index() {
        $users = User::orderBy('role')->orderBy('name')->get();
        return view('admin.users.index', compact('users'));
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
        return back()->with('status', $name . ' er slettet.');
    }
}
