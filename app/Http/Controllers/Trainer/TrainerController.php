<?php

namespace App\Http\Controllers\Trainer;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\Request;

class TrainerController extends Controller
{
    public function index(Request $request) {
        $user = $request->user();
        $courses = $user->isOwner()
            ? Course::with('trainer')->orderByDesc('is_active')->orderBy('title')->get()
            : Course::with('trainer')->where('trainer_id', $user->id)->get();
        return view('trainer.index', compact('courses'));
    }

    public function participants(Request $request, Course $course) {
        $this->authorize($request, $course);
        $enrollments = $course->enrollments()->with('user')->where('status','active')->orderBy('enrolled_at')->get();
        return view('trainer.participants', compact('course','enrollments'));
    }

    private function authorize(Request $request, Course $course): void {
        $u = $request->user();
        abort_unless($u->isOwner() || $course->trainer_id === $u->id, 403);
    }
}
