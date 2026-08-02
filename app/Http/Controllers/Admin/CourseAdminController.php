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
        return view('admin.courses.form', ['course' => new Course(['is_active' => false, 'max_participants' => 10, 'chat_enabled' => true]), 'trainers' => $this->trainers()]);
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

        // Deactivating hides the hold and 404s its page, but billing keeps
        // running — so a member would pay for something they cannot reach.
        $members = $course->memberCount();
        if ($course->is_active && ! $data['is_active'] && $members > 0) {
            return back()
                ->withErrors(['is_active' => 'Holdet har ' . $members . ' ' . ($members === 1 ? 'medlem' : 'medlemmer') . ' og kan ikke gøres inaktivt. Afmeld medlemmerne først.'])
                ->withInput();
        }
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
        $subject = match ($course->type) {
            Course::TYPE_FAELLES => 'Fællestræningen er',
            Course::TYPE_PERSONLIG => 'Den personlige træning er',
            default => 'Holdet er',
        };

        return $subject.' '.$verb.'.';
    }

    private function validateData(Request $request): array
    {
        // Price and capacity are meaningless for a fællestræning — it is free to
        // members and nobody registers — so the form hides them and they are not
        // required back.
        $isFaelles = $request->input('type') === Course::TYPE_FAELLES;

        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'type' => ['nullable', Rule::in(array_keys(Course::TYPES))],
            'description' => ['required', 'string', 'max:4000'],
            'trainer_ids' => ['required', 'array', 'min:1'],
            // Role-checked, not just exists: the picker only offers trainers/owners,
            // and a course trainer list holding anyone else leaves them with a stale
            // "Træner" badge and a broadcast button that fails.
            'trainer_ids.*' => ['integer', Rule::exists('users', 'id')->whereIn('role', ['owner', 'trainer'])],
            'image' => ['nullable', 'image', 'max:16384'],
            'video' => ['nullable', 'file', 'mimes:mp4,mov,avi,webm,m4v,mkv', 'max:512000'],
            'remove_video' => ['nullable', 'boolean'],
            'price_kr' => [Rule::requiredIf(! $isFaelles), 'nullable', 'numeric', 'min:0', 'max:100000'],
            'max_participants' => [Rule::requiredIf(! $isFaelles), 'nullable', 'integer', 'min:1', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
            'free_enrollment' => ['nullable', 'boolean'],
            'chat_enabled' => ['nullable', 'boolean'],
            'slots' => ['nullable', 'array'],
            'slots.*.weekday' => ['required', 'in:mon,tue,wed,thu,fri,sat,sun'],
            'slots.*.start' => ['nullable', 'date_format:H:i'],
            'slots.*.end' => ['nullable', 'date_format:H:i'],
        ]);
        $trainerIds = array_values(array_unique(array_map('intval', $data['trainer_ids'])));
        unset($data['trainer_ids']);
        $data['type'] = $data['type'] ?? Course::TYPE_HOLD;
        $data['is_active'] = $request->boolean('is_active');
        $data['free_enrollment'] = $request->boolean('free_enrollment');

        if ($isFaelles) {
            // Stored flat rather than left to the view: a fællestræning that ever
            // carried a price would start charging the moment it was retyped.
            $data['price_cents'] = 0;
            $data['max_participants'] = $data['max_participants'] ?? 100;
            $data['chat_enabled'] = $request->boolean('chat_enabled');
        } else {
            $data['price_cents'] = (int) round(((float) $data['price_kr']) * 100);
            $data['chat_enabled'] = true;
        }
        unset($data['price_kr']);

        $slots = $this->slotsFrom($data['slots'] ?? []);
        unset($data['video'], $data['remove_video'], $data['slots']);

        return [$data, $trainerIds, $slots];
    }

    /**
     * Training times, in calendar order. An end at or before its start keeps
     * only the start — a range ending before it begins would push the
     * next-occurrence maths backwards. Identical day+start pairs collapse.
     *
     * @return array<int, array{weekday:string, start_time:?string, end_time:?string}>
     */
    private function slotsFrom(array $input): array
    {
        $order = array_flip(array_keys(Course::WEEKDAYS));

        return collect($input)
            ->filter(fn ($s) => isset(Course::WEEKDAYS[$s['weekday'] ?? '']))
            ->map(function ($s) {
                $start = $s['start'] ?? null;
                $end = $s['end'] ?? null;
                if ($start && $end && $end <= $start) $end = null;
                if (! $start) $end = null;

                return ['weekday' => $s['weekday'], 'start_time' => $start ?: null, 'end_time' => $end ?: null];
            })
            ->unique(fn ($s) => $s['weekday'] . '|' . $s['start_time'])
            ->sortBy(fn ($s) => sprintf('%d%s', $order[$s['weekday']], $s['start_time'] ?? ''))
            ->values()
            ->all();
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
