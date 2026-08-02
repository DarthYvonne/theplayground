<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class UserAdminController extends Controller
{
    public function index() {
        $users = User::orderBy('role')->orderBy('name')->get();
        return view('admin.users.index', compact('users'));
    }

    public function edit(User $user) {
        return view('admin.users.edit', [
            'user' => $user,
            'trainerCourses' => $user->trainerCourses()->with('trainers')->orderBy('title')->get(),
            'eligibleTrainers' => User::whereIn('role', ['owner', 'trainer'])->where('id', '!=', $user->id)->orderBy('name')->get(),
        ]);
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
        // A course's trainer list must only hold trainers/owners, or the member
        // page shows a stale "Træner" badge and a broadcast button that fails.
        // Same rule as destroy(): move the holds first — see "Underviser på" below.
        if (! in_array($data['role'], ['owner', 'trainer'], true)) {
            $courses = $user->trainerCourses()->orderBy('title')->pluck('title');
            if ($courses->isNotEmpty()) {
                return back()->withErrors(['role' => $user->name . ' underviser på ' . $courses->join(', ', ' og ') . '. Skift træner på holdet herunder først.']);
            }
        }
        $user->update(['role' => $data['role']]);
        $label = ['user' => 'bruger', 'assistant' => 'assistent', 'trainer' => 'træner', 'owner' => 'ejer'][$data['role']] ?? $data['role'];
        return back()->with('status', $user->name . ' er nu ' . $label . '.');
    }

    /**
     * Swap this user out for another trainer on one course, without opening the
     * full course form. Other trainers on the course are left alone.
     */
    public function swapTrainer(Request $request, User $user, Course $course): RedirectResponse {
        $data = $request->validate([
            'trainer_id' => ['required', 'integer', Rule::exists('users', 'id')->whereIn('role', ['owner', 'trainer'])],
        ]);

        if (! $course->hasTrainer($user)) {
            return back()->withErrors(['trainer' => $user->name . ' underviser ikke på ' . $course->title . '.']);
        }
        if ((int) $data['trainer_id'] === $user->id) {
            return back()->withErrors(['trainer' => 'Vælg en anden træner.']);
        }

        $replacement = User::find((int) $data['trainer_id']);
        $course->trainers()->detach($user->id);
        $course->trainers()->syncWithoutDetaching([$replacement->id]);
        // A personlig træning is named after its trainer, so the title has to
        // follow — this path bypasses the course form entirely.
        $previousTitle = $course->title;
        $course->refreshPersonligTitle();

        return back()->with('status', $replacement->name . ' er nu træner på ' . $previousTitle . ' i stedet for ' . $user->name . '.');
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
        // courses.member_id carries no database-level foreign key (see the
        // migration — adding one rebuilds the table on SQLite and cascades into
        // course_schedules), so the dangling reference is cleared here instead.
        Course::where('member_id', $user->id)->update(['member_id' => null, 'member_claimed_at' => null]);
        $user->delete();
        return redirect()->route('admin.users.index')->with('status', $name . ' er slettet.');
    }
}
