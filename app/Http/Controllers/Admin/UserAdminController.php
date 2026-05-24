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
}
