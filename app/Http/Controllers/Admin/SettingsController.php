<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Setting;
use App\Support\StripeConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function revenue()
    {
        $activeEnrollments = Enrollment::where('status', 'active')->count();
        $monthlyCents = (int) Course::query()
            ->join('enrollments', 'enrollments.course_id', '=', 'courses.id')
            ->where('enrollments.status', 'active')
            ->sum('courses.price_cents');

        $perCourse = Course::query()
            ->withCount(['enrollments as active_count' => fn ($q) => $q->where('status', 'active')])
            ->orderByDesc('active_count')
            ->orderBy('title')
            ->get();

        return view('admin.settings.revenue', [
            'activeEnrollments' => $activeEnrollments,
            'monthlyCents' => $monthlyCents,
            'currency' => strtoupper(StripeConfig::currency()),
            'perCourse' => $perCourse,
        ]);
    }

    public function connections()
    {
        return view('admin.settings.connections', [
            'stripe' => [
                'key' => Setting::get('stripe_key'),
                'secret_set' => (bool) Setting::get('stripe_secret'),
                'webhook_set' => (bool) Setting::get('stripe_webhook_secret'),
                'currency' => StripeConfig::currency(),
                'configured' => StripeConfig::isConfigured(),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'stripe_key' => ['nullable','string','max:255'],
            'stripe_secret' => ['nullable','string','max:255'],
            'stripe_webhook_secret' => ['nullable','string','max:255'],
            'stripe_currency' => ['nullable','string','size:3'],
            'clear_secret' => ['nullable','boolean'],
            'clear_webhook' => ['nullable','boolean'],
        ]);

        if (!empty($data['stripe_key'])) Setting::put('stripe_key', trim($data['stripe_key']));
        if (!empty($data['stripe_secret'])) Setting::put('stripe_secret', trim($data['stripe_secret']));
        if (!empty($data['stripe_webhook_secret'])) Setting::put('stripe_webhook_secret', trim($data['stripe_webhook_secret']));
        if (!empty($data['stripe_currency'])) Setting::put('stripe_currency', strtolower(trim($data['stripe_currency'])));

        if ($request->boolean('clear_secret')) Setting::put('stripe_secret', null);
        if ($request->boolean('clear_webhook')) Setting::put('stripe_webhook_secret', null);

        return back()->with('status', 'Indstillinger gemt.');
    }

    public function testStripe(Request $request): RedirectResponse
    {
        if (!StripeConfig::isConfigured()) {
            return back()->withErrors(['stripe_secret' => 'Indtast en hemmelig nøgle først.']);
        }
        try {
            $res = \Illuminate\Support\Facades\Http::withToken(StripeConfig::secret())
                ->acceptJson()
                ->timeout(10)
                ->get('https://api.stripe.com/v1/account');
            if (!$res->ok()) {
                $msg = $res->json('error.message') ?? ('HTTP ' . $res->status());
                return back()->withErrors(['stripe_secret' => 'Stripe afviste nøglen: ' . $msg]);
            }
            $data = $res->json();
            $label = $data['business_profile']['name'] ?? $data['email'] ?? $data['id'] ?? 'konto';
            $mode = str_starts_with(StripeConfig::secret(), 'sk_live_') ? 'live' : 'test';
            return back()->with('status', "Stripe OK ({$mode}-tilstand) — forbundet som {$label}.");
        } catch (\Throwable $e) {
            return back()->withErrors(['stripe_secret' => 'Netværks-/parsing-fejl: ' . $e->getMessage()]);
        }
    }
}
