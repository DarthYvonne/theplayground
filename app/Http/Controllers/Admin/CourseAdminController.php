<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessVideoJob;
use App\Models\Course;
use App\Models\CourseCancellation;
use App\Models\User;
use App\Support\CalendarWeek;
use App\Support\ScheduleGrid;
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
        $courses = Course::with(['trainers', 'schedules'])->where('is_active', true)->orderBy('title')->get();

        $byDay = ScheduleGrid::byDay($courses, array_keys(Course::WEEKDAYS));
        $unscheduled = $courses->filter(fn ($c) => empty($c->weekdaysList()))->values();
        $weekendCourses = collect($byDay['sat'] ?? [])->concat($byDay['sun'] ?? [])
            ->map(fn ($slot) => $slot->course)->unique('id')->values();

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
        [$data, $trainerIds, $slots] = $this->validateData($request);
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
        $this->syncSchedules($course, $slots);
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
        [$data, $trainerIds, $slots] = $this->validateData($request);
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
        $this->syncSchedules($course, $slots);
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
            'weekdays' => ['nullable', 'array'],
            'weekdays.*' => ['in:mon,tue,wed,thu,fri,sat,sun'],
            'schedule' => ['nullable', 'array'],
            'schedule.*.start' => ['nullable', 'date_format:H:i'],
            'schedule.*.end' => ['nullable', 'date_format:H:i'],
        ]);
        $trainerIds = array_values(array_unique(array_map('intval', $data['trainer_ids'])));
        unset($data['trainer_ids']);
        $data['price_cents'] = (int) round(((float) $data['price_kr']) * 100);
        unset($data['price_kr']);
        $data['is_active'] = $request->boolean('is_active');
        $data['free_enrollment'] = $request->boolean('free_enrollment');

        $slots = $this->slotsFrom($data['weekdays'] ?? [], $data['schedule'] ?? []);
        unset($data['video'], $data['remove_video'], $data['weekdays'], $data['schedule']);

        return [$data, $trainerIds, $slots];
    }

    /**
     * One slot per checked weekday, each with its own hours. A day whose end is
     * at or before its start keeps only the start — a range that ends before it
     * begins would push the calendar's next-occurrence maths backwards.
     *
     * @return array<int, array{weekday:string, start_time:?string, end_time:?string}>
     */
    private function slotsFrom(array $weekdays, array $schedule): array
    {
        $slots = [];
        foreach (array_keys(Course::WEEKDAYS) as $day) {
            if (! in_array($day, $weekdays, true)) continue;
            $start = $schedule[$day]['start'] ?? null;
            $end = $schedule[$day]['end'] ?? null;
            if ($start && $end && $end <= $start) $end = null;
            $slots[] = ['weekday' => $day, 'start_time' => $start ?: null, 'end_time' => $end ?: null];
        }

        return $slots;
    }

    /** @param array<int, array{weekday:string, start_time:?string, end_time:?string}> $slots */
    private function syncSchedules(Course $course, array $slots): void
    {
        $course->schedules()->delete();
        if ($slots) $course->schedules()->createMany($slots);
        $course->unsetRelation('schedules');
    }

    private function trainers()
    {
        return User::whereIn('role', ['owner', 'trainer'])->orderBy('name')->get();
    }
}
