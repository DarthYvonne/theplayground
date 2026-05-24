<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use App\Support\StripeConfig;

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

    public function other()
    {
        return view('admin.settings.other');
    }
}
