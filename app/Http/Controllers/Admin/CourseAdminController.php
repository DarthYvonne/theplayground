<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\User;
use App\Support\StripeConfig;
use App\Support\StripeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CourseAdminController extends Controller
{
    public function index() {
        $courses = Course::with('trainer')->withCount(['enrollments as active_enrollments_count' => fn ($q) => $q->where('status','active')])->orderByDesc('created_at')->get();
        return view('admin.courses.index', compact('courses'));
    }

    public function calendar() {
        $courses = Course::with('trainer')->where('is_active', true)->orderBy('start_time')->orderBy('title')->get();

        $byDay = [];
        foreach (array_keys(Course::WEEKDAYS) as $day) $byDay[$day] = [];
        foreach ($courses as $c) {
            foreach ($c->weekdaysList() as $day) {
                if (isset($byDay[$day])) $byDay[$day][] = $c;
            }
        }
        $unscheduled = $courses->filter(fn ($c) => empty($c->weekdaysList()))->values();

        return view('admin.courses.calendar', compact('byDay', 'unscheduled'));
    }

    public function create() {
        return view('admin.courses.form', ['course' => new Course(['is_active' => false, 'max_participants' => 10]), 'trainers' => $this->trainers()]);
    }

    public function store(Request $request): RedirectResponse {
        $data = $this->validateData($request);
        if ($request->hasFile('image')) $data['image_path'] = $request->file('image')->store('courses', 'public');
        $course = Course::create($data);
        $this->syncStripe($course);
        return redirect()->route('admin.courses.edit', $course)->with('status', $this->saveMessage($course, 'oprettet'));
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
        $priceChanged = (int) $data['price_cents'] !== (int) $course->price_cents;
        $course->update($data);
        $this->syncStripe($course, $priceChanged);
        return back()->with('status', $this->saveMessage($course, 'opdateret'));
    }

    public function destroy(Course $course): RedirectResponse {
        if ($course->stripe_product_id && StripeConfig::isConfigured()) {
            if ($course->stripe_price_id) StripeService::archivePrice($course->stripe_price_id);
            StripeService::archiveProduct($course->stripe_product_id);
        }
        if ($course->image_path) Storage::disk('public')->delete($course->image_path);
        $course->delete();
        return redirect()->route('admin.courses.index')->with('status', 'Holdet er slettet.');
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
            'trainer_id' => ['required','exists:users,id'],
            'image' => ['nullable','image','max:16384'],
            'price_cents' => ['required','integer','min:0','max:10000000'],
            'max_participants' => ['required','integer','min:1','max:1000'],
            'is_active' => ['nullable','boolean'],
            'start_time' => ['nullable','date_format:H:i'],
            'end_time' => ['nullable','date_format:H:i'],
            'weekdays' => ['nullable','array'],
            'weekdays.*' => ['in:mon,tue,wed,thu,fri,sat,sun'],
        ]);
        $data['is_active'] = $request->boolean('is_active');
        $data['weekdays'] = !empty($data['weekdays']) ? implode(',', $data['weekdays']) : null;
        return $data;
    }

    private function trainers() {
        return User::whereIn('role', ['owner','trainer'])->orderBy('name')->get();
    }
}
