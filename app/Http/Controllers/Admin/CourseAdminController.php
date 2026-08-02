<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessVideoJob;
use App\Models\Course;
use App\Models\CourseCancellation;
use App\Models\User;
use App\Support\CalendarWeek;
use App\Support\Contact;
use App\Support\ScheduleGrid;
use App\Support\TrainerClash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
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

    public function create(Request $request)
    {
        // "+ Opret ny" carries the type of the page it was clicked from, so the
        // form opens on the right kind rather than always defaulting to Hold.
        $type = $request->query('type');
        $type = isset(Course::TYPES[$type]) ? $type : Course::TYPE_HOLD;

        return view('admin.courses.form', [
            'course' => new Course(['is_active' => false, 'max_participants' => 10, 'chat_enabled' => true, 'type' => $type]),
            'trainers' => $this->trainers(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        [$data, $trainerIds, $slots] = $this->validateData($request);
        // A personlig træning shows its trainer's photo, so an upload would only
        // ever be dead weight — the form disables the inputs, this enforces it.
        $isPersonlig = ($data['type'] ?? null) === Course::TYPE_PERSONLIG;
        if (! $isPersonlig && $request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('courses', 'public');
        }
        $videoPath = null;
        if (! $isPersonlig && $request->hasFile('video')) {
            $videoPath = $this->storeCourseVideo($request->file('video'));
            $data['video_path'] = $videoPath;
            $data['video_processing_status'] = 'pending';
        }
        $course = Course::create($data);
        $course->trainers()->sync($trainerIds);
        $this->syncSchedules($course, $slots);
        $course->refreshPersonligTitle();
        if ($videoPath) {
            ProcessVideoJob::dispatch(Course::class, $course->id, $videoPath, 'course_videos', true);
        }

        // Back to the form, unless the creator assigned somebody else as the
        // trainer — then it is not theirs to edit and the form would 403.
        $canEdit = $request->user()->isOwner() || $course->hasTrainer($request->user());
        $target = $canEdit
            ? redirect()->route('admin.courses.edit', $course)
            : redirect()->route(match ($course->type) {
                Course::TYPE_FAELLES => 'faellestraening.index',
                Course::TYPE_PERSONLIG => 'personlig.index',
                default => 'catalog',
            });

        return $target->with('status', $this->saveMessage($course, 'oprettet'));
    }

    public function edit(Request $request, Course $course)
    {
        $this->authorizeEdit($request, $course);

        return view('admin.courses.form', ['course' => $course, 'trainers' => $this->trainers()]);
    }

    /**
     * A trainer maintains what they train — including fixing a mistyped invite on
     * their own personlig træning. Somebody else's course is not theirs to edit.
     */
    private function authorizeEdit(Request $request, Course $course): void
    {
        $user = $request->user();
        abort_unless($user->isOwner() || $course->hasTrainer($user), 403);
    }

    public function update(Request $request, Course $course): RedirectResponse
    {
        $this->authorizeEdit($request, $course);
        [$data, $trainerIds, $slots] = $this->validateData($request, $course);

        // Deactivating hides the hold and 404s its page, but billing keeps
        // running — so a member would pay for something they cannot reach.
        $members = $course->memberCount();
        if ($course->is_active && ! $data['is_active'] && $members > 0) {
            return back()
                ->withErrors(['is_active' => 'Holdet har ' . $members . ' ' . ($members === 1 ? 'medlem' : 'medlemmer') . ' og kan ikke sættes på passiv. Afmeld medlemmerne først.'])
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
        $course->refreshPersonligTitle();
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

    /**
     * @param  Course|null $course  the one being edited; its type wins over the
     *                              request, so an existing training cannot be
     *                              converted into another kind by a stray field.
     */
    private function validateData(Request $request, ?Course $course = null): array
    {
        // Price and capacity are meaningless for a fællestræning — it is free to
        // members and nobody registers. A personlig træning has no capacity, no
        // typed title and no description at all. The form omits each of those, so
        // they are not required back.
        $type = $course?->type ?? $request->input('type');
        $isFaelles = $type === Course::TYPE_FAELLES;
        $isPersonlig = $type === Course::TYPE_PERSONLIG;

        $rules = [
            'title' => [Rule::requiredIf(! $isPersonlig), 'nullable', 'string', 'max:160'],
            'type' => ['nullable', Rule::in(array_keys(Course::TYPES))],
            'description' => [Rule::requiredIf(! $isPersonlig), 'nullable', 'string', 'max:4000'],
            'trainer_ids' => ['required', 'array', $isPersonlig ? 'size:1' : 'min:1'],
            // Role-checked, not just exists: the picker only offers trainers/owners,
            // and a course trainer list holding anyone else leaves them with a stale
            // "Træner" badge and a broadcast button that fails.
            'trainer_ids.*' => ['integer', Rule::exists('users', 'id')->whereIn('role', ['owner', 'trainer'])],
            'image' => ['nullable', 'image', 'max:16384'],
            'video' => ['nullable', 'file', 'mimes:mp4,mov,avi,webm,m4v,mkv', 'max:512000'],
            'remove_video' => ['nullable', 'boolean'],
            'price_kr' => [Rule::requiredIf(! $isFaelles), 'nullable', 'numeric', 'min:0', 'max:100000'],
            'max_participants' => [Rule::requiredIf(! $isFaelles && ! $isPersonlig), 'nullable', 'integer', 'min:1', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
            'free_enrollment' => ['nullable', 'boolean'],
            'chat_enabled' => ['nullable', 'boolean'],
            'slots' => ['nullable', 'array'],
            'slots.*.weekday' => ['required', 'in:mon,tue,wed,thu,fri,sat,sun'],
            'slots.*.start' => ['nullable', 'date_format:H:i'],
            'slots.*.end' => ['nullable', 'date_format:H:i'],
        ];

        if ($isPersonlig) {
            $rules['member_id'] = ['nullable', 'integer', 'exists:users,id'];
            $rules['member_invite_email'] = ['nullable', 'email', 'max:255'];
            $rules['member_invite_phone'] = ['nullable', 'string', 'max:40'];
        }

        $validator = Validator::make($request->all(), $rules);
        if ($isPersonlig) {
            $validator->after(function ($v) use ($request) {
                if (! $request->filled('member_id') && ! $request->filled('member_invite_email') && ! $request->filled('member_invite_phone')) {
                    $v->errors()->add('member_id', 'Vælg et medlem, eller skriv en e-mail eller et telefonnummer.');
                }
                $trainerIds = array_map('intval', (array) $request->input('trainer_ids', []));
                if ($request->filled('member_id') && in_array((int) $request->input('member_id'), $trainerIds, true)) {
                    $v->errors()->add('member_id', 'Træneren kan ikke også være medlemmet.');
                }
            });
        }
        // Every type shares one calendar: a trainer running a hold is unavailable
        // for a fællestræning or a 1:1 at the same hour, and vice versa.
        $validator->after(function ($v) use ($request, $course) {
            $clash = TrainerClash::find(
                array_values(array_unique(array_map('intval', (array) $request->input('trainer_ids', [])))),
                $this->slotsFrom((array) $request->input('slots', [])),
                $course
            );
            if ($clash) $v->errors()->add('slots', $clash);
        });
        $data = $validator->validate();

        $trainerIds = array_values(array_unique(array_map('intval', $data['trainer_ids'])));
        unset($data['trainer_ids']);
        $data['type'] = $course?->type ?? $data['type'] ?? Course::TYPE_HOLD;
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

        if ($isPersonlig) {
            // One seat, held by name — written truthfully rather than left to the
            // column default. The title is generated once the trainer is synced.
            $data['max_participants'] = 1;
            $data['description'] = '';
            $data['title'] = Course::PERSONLIG_TITLE;
            // A 1:1 has no catalogue entry to hold back — it is agreed with one
            // named person and it books a slot. "Passive but scheduled" is not a
            // state it can meaningfully be in, so the form omits the switch and
            // it is always active. Delete it instead.
            $data['is_active'] = true;
            [$data['member_id'], $data['member_invite_email'], $data['member_invite_phone']] = $this->resolveMember($request);
        } else {
            // Retyping away from personlig must not leave a stray member behind.
            $data['member_id'] = null;
            $data['member_invite_email'] = null;
            $data['member_invite_phone'] = null;
        }

        $slots = $this->slotsFrom($data['slots'] ?? []);
        unset($data['video'], $data['remove_video'], $data['slots']);

        return [$data, $trainerIds, $slots];
    }

    /**
     * Who the personlig træning belongs to.
     *
     * A typed e-mail that already belongs to a user links immediately, so an
     * existing member never sits in the pending state where a claim could race.
     * A phone is stored as typed alongside its comparable form.
     *
     * @return array{0: ?int, 1: ?string, 2: ?string}
     */
    private function resolveMember(Request $request): array
    {
        $memberId = $request->filled('member_id') ? (int) $request->input('member_id') : null;
        $email = Contact::normalizeEmail($request->input('member_invite_email'));
        $phone = Contact::normalizePhone($request->input('member_invite_phone'));

        if (! $memberId && $email) {
            $memberId = User::whereRaw('lower(email) = ?', [$email])->value('id');
        }
        if (! $memberId && $phone) {
            // Only when it is unambiguous — a shared household number must not
            // pick someone at random.
            $matches = User::where('phone_normalized', $phone)->pluck('id');
            if ($matches->count() === 1) {
                $memberId = (int) $matches->first();
            }
        }

        return [$memberId, $email, $phone];
    }

    /** Member picker for the personlig-træning form. Name, e-mail or phone. */
    public function memberSearch(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $phone = Contact::normalizePhone($q);
        $email = Contact::normalizeEmail($q);

        $users = User::query()
            ->when($q !== '', fn ($u) => $u->where(fn ($u) => $u
                ->where('name', 'like', "%{$q}%")
                ->orWhereRaw('lower(email) like ?', ["%{$email}%"])
                ->when($phone, fn ($u) => $u->orWhere('phone_normalized', 'like', "%{$phone}%"))))
            ->orderBy('name')
            ->limit(20)
            ->get();

        return response()->json(['results' => $users->map(fn (User $u) => [
            'id' => $u->id,
            'label' => $u->name,
            'sub' => trim($u->email.($u->phone ? ' · '.$u->phone : '')),
            'picture_url' => $u->pictureUrl(),
            'initials' => $u->initials(),
        ])->all()]);
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
