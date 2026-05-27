<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessVideoJob;
use App\Models\Course;
use App\Models\CourseCancellation;
use App\Models\User;
use App\Support\CalendarWeek;
use App\Support\StripeConfig;
use App\Support\StripeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CourseAdminController extends Controller
{
    public function index() {
        $courses = Course::with('trainers')->withCount(['enrollments as active_enrollments_count' => fn ($q) => $q->where('status','active')])->orderByDesc('created_at')->get();
        return view('admin.courses.index', compact('courses'));
    }

    public function calendar(Request $request) {
        $ctx = CalendarWeek::resolveContext($request);
        $courses = Course::with('trainers')->where('is_active', true)->orderBy('start_time')->orderBy('title')->get();

        $byDay = [];
        foreach (array_keys(Course::WEEKDAYS) as $day) $byDay[$day] = [];
        foreach ($courses as $c) {
            foreach ($c->weekdaysList() as $day) {
                if (isset($byDay[$day])) $byDay[$day][] = $c;
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

    public function create() {
        return view('admin.courses.form', ['course' => new Course(['is_active' => false, 'max_participants' => 10]), 'trainers' => $this->trainers()]);
    }

    public function store(Request $request): RedirectResponse {
        [$data, $trainerIds] = $this->validateData($request);
        if ($request->hasFile('image')) $data['image_path'] = $request->file('image')->store('courses', 'public');
        $videoPath = null;
        if ($request->hasFile('video')) {
            $videoPath = $this->storeCourseVideo($request->file('video'));
            $data['video_path'] = $videoPath;
            $data['video_processing_status'] = 'pending';
        }
        $course = Course::create($data);
        $course->trainers()->sync($trainerIds);
        $this->syncStripe($course);
        if ($videoPath) {
            ProcessVideoJob::dispatch(Course::class, $course->id, $videoPath, 'course_videos', true);
        }
        return redirect()->route('admin.courses.edit', $course)->with('status', $this->saveMessage($course, 'oprettet'));
    }

    public function edit(Course $course) {
        return view('admin.courses.form', ['course' => $course, 'trainers' => $this->trainers()]);
    }

    public function update(Request $request, Course $course): RedirectResponse {
        [$data, $trainerIds] = $this->validateData($request);
        if ($request->hasFile('image')) {
            if ($course->image_path) Storage::disk('public')->delete($course->image_path);
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
        $priceChanged = (int) $data['price_cents'] !== (int) $course->price_cents;
        $course->update($data);
        $course->trainers()->sync($trainerIds);
        $this->syncStripe($course, $priceChanged);
        if ($newVideoPath) {
            ProcessVideoJob::dispatch(Course::class, $course->id, $newVideoPath, 'course_videos', true);
        }
        return back()->with('status', $this->saveMessage($course, 'opdateret'));
    }

    public function destroy(Course $course): RedirectResponse {
        if ($course->stripe_product_id && StripeConfig::isConfigured()) {
            if ($course->stripe_price_id) StripeService::archivePrice($course->stripe_price_id);
            StripeService::archiveProduct($course->stripe_product_id);
        }
        if ($course->image_path) Storage::disk('public')->delete($course->image_path);
        $this->deleteCourseVideoFiles($course);
        $course->delete();
        return redirect()->route('admin.courses.index')->with('status', 'Holdet er slettet.');
    }

    private function storeCourseVideo(\Illuminate\Http\UploadedFile $file): string
    {
        $name = \Illuminate\Support\Str::ulid() . '.' . strtolower($file->getClientOriginalExtension() ?: $file->extension());
        return $file->storeAs(now()->format('Y/m'), $name, 'course_videos');
    }

    private function deleteCourseVideoFiles(Course $course): void
    {
        $disk = Storage::disk('course_videos');
        foreach (['video_path','original_video_path','video_thumbnail_path'] as $col) {
            if ($course->{$col}) $disk->delete($course->{$col});
        }
    }

    /**
     * Create or update the matching Stripe Product + monthly Price. Stripe
     * Prices are immutable, so changing the amount means archiving the old
     * price and creating a new one. No-ops cleanly if Stripe isn't configured.
     */
    private function syncStripe(Course $course, bool $priceChanged = true): void
    {
        if (!StripeConfig::isConfigured()) return;
        try {
            if (!$course->stripe_product_id) {
                $product = StripeService::createProduct($course->title, $course->description);
                $course->stripe_product_id = $product['id'];
            } else {
                StripeService::updateProduct($course->stripe_product_id, $course->title, $course->description);
            }
            if (!$course->stripe_price_id || $priceChanged) {
                if ($course->stripe_price_id) StripeService::archivePrice($course->stripe_price_id);
                $price = StripeService::createRecurringMonthlyPrice($course->stripe_product_id, (int) $course->price_cents);
                $course->stripe_price_id = $price['id'];
            }
            $course->save();
        } catch (\Throwable $e) {
            // Don't break the admin flow if Stripe is misconfigured; surface via session flash.
            session()->flash('status', 'Gemt lokalt, men synkronisering med Stripe fejlede: ' . $e->getMessage());
        }
    }

    private function saveMessage(Course $course, string $verb): string
    {
        if (!StripeConfig::isConfigured()) return 'Holdet er ' . $verb . ' (Stripe er ikke konfigureret — kun gemt lokalt).';
        if (!$course->stripe_product_id) return 'Holdet er ' . $verb . ' (Stripe-synkronisering sprunget over).';
        return 'Holdet er ' . $verb . ' · Stripe-produkt ' . $course->stripe_product_id . '.';
    }

    private function validateData(Request $request): array {
        $data = $request->validate([
            'title' => ['required','string','max:160'],
            'description' => ['required','string','max:4000'],
            'trainer_ids' => ['required','array','min:1'],
            'trainer_ids.*' => ['integer','exists:users,id'],
            'image' => ['nullable','image','max:16384'],
            'video' => ['nullable','file','mimes:mp4,mov,avi,webm,m4v,mkv','max:512000'],
            'remove_video' => ['nullable','boolean'],
            'price_kr' => ['required','numeric','min:0','max:100000'],
            'max_participants' => ['required','integer','min:1','max:1000'],
            'is_active' => ['nullable','boolean'],
            'free_enrollment' => ['nullable','boolean'],
            'start_time' => ['nullable','date_format:H:i'],
            'end_time' => ['nullable','date_format:H:i'],
            'weekdays' => ['nullable','array'],
            'weekdays.*' => ['in:mon,tue,wed,thu,fri,sat,sun'],
        ]);
        $trainerIds = array_values(array_unique(array_map('intval', $data['trainer_ids'])));
        unset($data['trainer_ids']);
        $data['price_cents'] = (int) round(((float) $data['price_kr']) * 100);
        unset($data['price_kr']);
        $data['is_active'] = $request->boolean('is_active');
        $data['free_enrollment'] = $request->boolean('free_enrollment');
        $data['weekdays'] = !empty($data['weekdays']) ? implode(',', $data['weekdays']) : null;
        unset($data['video'], $data['remove_video']);
        return [$data, $trainerIds];
    }

    private function trainers() {
        return User::whereIn('role', ['owner','trainer'])->orderBy('name')->get();
    }
}
