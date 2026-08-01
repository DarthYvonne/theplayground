<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessVideoJob;
use App\Models\Course;
use App\Models\CourseCancellation;
use App\Models\User;
use App\Support\CalendarWeek;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CourseAdminController extends Controller
{
    public function index()
    {
        $courses = Course::with('trainers')->withCount(['enrollments as active_enrollments_count' => fn ($q) => $q->where('status', 'active')])->orderByDesc('created_at')->get();

        return view('admin.courses.index', compact('courses'));
    }

    public function calendar(Request $request)
    {
        $ctx = CalendarWeek::resolveContext($request);
        $courses = Course::with('trainers')->where('is_active', true)->orderBy('start_time')->orderBy('title')->get();

        $byDay = [];
        foreach (array_keys(Course::WEEKDAYS) as $day) {
            $byDay[$day] = [];
        }
        foreach ($courses as $c) {
            foreach ($c->weekdaysList() as $day) {
                if (isset($byDay[$day])) {
                    $byDay[$day][] = $c;
                }
            }
        }
        $unscheduled = $courses->filter(fn ($c) => empty($c->weekdaysList()))->values();
        $weekendCourses = collect($byDay['sat'] ?? [])->concat($byDay['sun'] ?? [])->unique('id')->values();

        $cancelledMap = CourseCancellation::mapForRange($courses->pluck('id')->all(), $ctx['rangeStart'], $ctx['rangeEnd']);
        $monday = $ctx['monday'];
        $monthAnchor = $ctx['monthAnchor'];
        $view = $ctx['view'];

        return view('admin.courses.calendar', compact(
            'byDay', 'unscheduled', 'weekendCourses',
            'monday', 'monthAnchor', 'view', 'cancelledMap'
        ));
    }

    public function create()
    {
        return view('admin.courses.form', ['course' => new Course(['is_active' => false, 'max_participants' => 10]), 'trainers' => $this->trainers()]);
    }

    public function store(Request $request): RedirectResponse
    {
        [$data, $trainerIds] = $this->validateData($request);
        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('courses', 'public');
        }
        $videoPath = null;
        if ($request->hasFile('video')) {
            $videoPath = $this->storeCourseVideo($request->file('video'));
            $data['video_path'] = $videoPath;
            $data['video_processing_status'] = 'pending';
        }
        $course = Course::create($data);
        $course->trainers()->sync($trainerIds);
        if ($videoPath) {
            ProcessVideoJob::dispatch(Course::class, $course->id, $videoPath, 'course_videos', true);
        }

        return redirect()->route('admin.courses.edit', $course)->with('status', $this->saveMessage($course, 'oprettet'));
    }

    public function edit(Course $course)
    {
        return view('admin.courses.form', ['course' => $course, 'trainers' => $this->trainers()]);
    }

    public function update(Request $request, Course $course): RedirectResponse
    {
        [$data, $trainerIds] = $this->validateData($request);
        if ($request->hasFile('image')) {
            if ($course->image_path) {
                Storage::disk('public')->delete($course->image_path);
            }
            $data['image_path'] = $request->file('image')->store('courses', 'public');
        }
        $newVideoPath = null;
        if ($request->boolean('remove_video')) {
            $this->deleteCourseVideoFiles($course);
            $data['video_path'] = null;
            $data['original_video_path'] = null;
            $data['video_processing_status'] = null;
            $data['video_thumbnail_path'] = null;
        }
        if ($request->hasFile('video')) {
            $this->deleteCourseVideoFiles($course);
            $newVideoPath = $this->storeCourseVideo($request->file('video'));
            $data['video_path'] = $newVideoPath;
            $data['original_video_path'] = null;
            $data['video_processing_status'] = 'pending';
            $data['video_thumbnail_path'] = null;
        }
        $course->update($data);
        $course->trainers()->sync($trainerIds);
        if ($newVideoPath) {
            ProcessVideoJob::dispatch(Course::class, $course->id, $newVideoPath, 'course_videos', true);
        }

        return back()->with('status', $this->saveMessage($course, 'opdateret'));
    }

    public function destroy(Course $course): RedirectResponse
    {
        if ($course->image_path) {
            Storage::disk('public')->delete($course->image_path);
        }
        $this->deleteCourseVideoFiles($course);
        $course->delete();

        return redirect()->route('admin.courses.index')->with('status', 'Holdet er slettet.');
    }

    private function storeCourseVideo(UploadedFile $file): string
    {
        $name = Str::ulid().'.'.strtolower($file->getClientOriginalExtension() ?: $file->extension());

        return $file->storeAs(now()->format('Y/m'), $name, 'course_videos');
    }

    private function deleteCourseVideoFiles(Course $course): void
    {
        $disk = Storage::disk('course_videos');
        foreach (['video_path', 'original_video_path', 'video_thumbnail_path'] as $col) {
            if ($course->{$col}) {
                $disk->delete($course->{$col});
            }
        }
    }

    private function saveMessage(Course $course, string $verb): string
    {
        return 'Holdet er '.$verb.'.';
    }

    private function validateData(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'description' => ['required', 'string', 'max:4000'],
            'trainer_ids' => ['required', 'array', 'min:1'],
            // Role-checked, not just exists: the picker only offers trainers/owners,
            // and a course trainer list holding anyone else leaves them with a stale
            // "Træner" badge and a broadcast button that fails.
            'trainer_ids.*' => ['integer', Rule::exists('users', 'id')->whereIn('role', ['owner', 'trainer'])],
            'image' => ['nullable', 'image', 'max:16384'],
            'video' => ['nullable', 'file', 'mimes:mp4,mov,avi,webm,m4v,mkv', 'max:512000'],
            'remove_video' => ['nullable', 'boolean'],
            'price_kr' => ['required', 'numeric', 'min:0', 'max:100000'],
            'max_participants' => ['required', 'integer', 'min:1', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
            'free_enrollment' => ['nullable', 'boolean'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'weekdays' => ['nullable', 'array'],
            'weekdays.*' => ['in:mon,tue,wed,thu,fri,sat,sun'],
        ]);
        $trainerIds = array_values(array_unique(array_map('intval', $data['trainer_ids'])));
        unset($data['trainer_ids']);
        $data['price_cents'] = (int) round(((float) $data['price_kr']) * 100);
        unset($data['price_kr']);
        $data['is_active'] = $request->boolean('is_active');
        $data['free_enrollment'] = $request->boolean('free_enrollment');
        $data['weekdays'] = ! empty($data['weekdays']) ? implode(',', $data['weekdays']) : null;
        unset($data['video'], $data['remove_video']);

        return [$data, $trainerIds];
    }

    private function trainers()
    {
        return User::whereIn('role', ['owner', 'trainer'])->orderBy('name')->get();
    }
}
