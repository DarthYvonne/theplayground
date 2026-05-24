<?php

namespace App\Http\Controllers\Trainer;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\Request;

class TrainerController extends Controller
{
    public function index(Request $request) {
        $user = $request->user();
        $courses = Course::with('trainer')
            ->where('trainer_id', $user->id)
            ->orderByDesc('is_active')
            ->orderBy('title')
            ->get();
        return view('trainer.index', compact('courses'));
    }

    public function calendar(Request $request) {
        $user = $request->user();
        $courses = Course::with('trainer')
            ->where('trainer_id', $user->id)
            ->where('is_active', true)
            ->orderBy('start_time')
            ->orderBy('title')
            ->get();

        $byDay = [];
        foreach (array_keys(Course::WEEKDAYS) as $day) $byDay[$day] = [];
        foreach ($courses as $c) {
            foreach ($c->weekdaysList() as $day) {
                if (isset($byDay[$day])) $byDay[$day][] = $c;
            }
        }
        $unscheduled = $courses->filter(fn ($c) => empty($c->weekdaysList()))->values();

        return view('trainer.calendar', compact('byDay', 'unscheduled'));
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
