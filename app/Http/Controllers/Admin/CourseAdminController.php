<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CourseAdminController extends Controller
{
    public function index() {
        $courses = Course::with('trainer')->withCount(['enrollments as active_enrollments_count' => fn ($q) => $q->where('status','active')])->orderByDesc('created_at')->get();
        return view('admin.courses.index', compact('courses'));
    }

    public function create() {
        return view('admin.courses.form', ['course' => new Course(['is_active' => false, 'max_participants' => 10]), 'trainers' => $this->trainers()]);
    }

    public function store(Request $request): RedirectResponse {
        $data = $this->validateData($request);
        if ($request->hasFile('image')) $data['image_path'] = $request->file('image')->store('courses', 'public');
        $course = Course::create($data);
        return redirect()->route('admin.courses.edit', $course)->with('status', 'Course created.');
    }

    public function edit(Course $course) {
        return view('admin.courses.form', ['course' => $course, 'trainers' => $this->trainers()]);
    }

    public function update(Request $request, Course $course): RedirectResponse {
        $data = $this->validateData($request);
        if ($request->hasFile('image')) {
            if ($course->image_path) Storage::disk('public')->delete($course->image_path);
            $data['image_path'] = $request->file('image')->store('courses', 'public');
        }
        $course->update($data);
        return back()->with('status', 'Course updated.');
    }

    public function destroy(Course $course): RedirectResponse {
        if ($course->image_path) Storage::disk('public')->delete($course->image_path);
        $course->delete();
        return redirect()->route('admin.courses.index')->with('status', 'Course deleted.');
    }

    private function validateData(Request $request): array {
        return $request->validate([
            'title' => ['required','string','max:160'],
            'description' => ['required','string','max:4000'],
            'trainer_id' => ['required','exists:users,id'],
            'image' => ['nullable','image','max:5120'],
            'price_cents' => ['required','integer','min:0','max:10000000'],
            'max_participants' => ['required','integer','min:1','max:1000'],
            'is_active' => ['nullable','boolean'],
        ]) + ['is_active' => $request->boolean('is_active')];
    }

    private function trainers() {
        return User::whereIn('role', ['owner','trainer'])->orderBy('name')->get();
    }
}
