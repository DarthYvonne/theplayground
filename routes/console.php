<?php

use App\Models\Enrollment;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Pending enrollments are pre-created when a user is redirected to Stripe Checkout.
// If they abandon the flow we never hear back, so we sweep them after 30 minutes
// to keep the user's billing view clean and let them retry from a fresh state.
Schedule::call(function () {
    Enrollment::where('status', 'pending')
        ->where('created_at', '<', now()->subMinutes(30))
        ->update(['status' => 'canceled', 'canceled_at' => now()]);
})->everyFiveMinutes()->name('expire-pending-enrollments')->withoutOverlapping();
