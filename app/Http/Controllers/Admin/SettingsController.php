<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\FloatingBooking;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function revenue(Request $request)
    {
        $period = $request->query('period') === 'year' ? 'year' : 'month';
        $now = Carbon::now();

        if ($period === 'year') {
            $year = (int) ($request->query('date') ?: $now->year);
            $start = Carbon::create($year, 1, 1)->startOfDay();
            $end = Carbon::create($year, 12, 31)->endOfDay();
            $periodLabel = (string) $year;
        } else {
            $raw = (string) ($request->query('date') ?: $now->format('Y-m'));
            $parts = explode('-', $raw);
            $y = (int) ($parts[0] ?? $now->year);
            $m = (int) ($parts[1] ?? $now->month);
            if ($m < 1 || $m > 12) { $m = $now->month; }
            $start = Carbon::create($y, $m, 1)->startOfDay();
            $end = $start->copy()->endOfMonth();
            $periodLabel = $this->monthLabelDa($m) . ' ' . $y;
        }

        // Hold (subscriptions): for each course, count enrollments that were active during the period,
        // i.e. enrolled before period end AND not canceled before period start.
        $perCourse = Course::query()
            ->withCount(['enrollments as active_in_period' => function ($q) use ($start, $end) {
                $q->where('enrolled_at', '<=', $end)
                  ->where(function ($q2) use ($start) {
                      $q2->whereNull('canceled_at')->orWhere('canceled_at', '>=', $start);
                  });
            }])
            ->orderByDesc('active_in_period')
            ->orderBy('title')
            ->get();

        $monthsInPeriod = $period === 'year' ? 12 : 1;
        $holdCentsInPeriod = 0;
        foreach ($perCourse as $c) {
            $holdCentsInPeriod += (int) $c->price_cents * (int) $c->active_in_period * $monthsInPeriod;
        }

        // Live "right now" MRR (always based on currently-active enrollments).
        $activeEnrollmentsNow = Enrollment::where('status', 'active')->count();
        $monthlyCentsNow = (int) Course::query()
            ->join('enrollments', 'enrollments.course_id', '=', 'courses.id')
            ->where('enrollments.status', 'active')
            ->sum('courses.price_cents');

        // Members only (excluding owner/trainer/assistant).
        $membersCount = User::where('role', 'user')->count();

        // Floating revenue: sum amount_cents for bookings paid within the period.
        $floatingCentsInPeriod = (int) FloatingBooking::query()
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$start, $end])
            ->sum('amount_cents');

        $floatingBookingsCount = (int) FloatingBooking::query()
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$start, $end])
            ->count();

        // Period options for selector: last 12 months + last 3 years.
        $monthOptions = [];
        for ($i = 0; $i < 12; $i++) {
            $d = $now->copy()->startOfMonth()->subMonths($i);
            $monthOptions[] = [
                'value' => $d->format('Y-m'),
                'label' => $this->monthLabelDa((int) $d->month) . ' ' . $d->year,
            ];
        }
        $yearOptions = [];
        for ($i = 0; $i < 4; $i++) {
            $y = $now->year - $i;
            $yearOptions[] = ['value' => (string) $y, 'label' => (string) $y];
        }

        $selectedValue = $period === 'year' ? (string) $start->year : $start->format('Y-m');

        return view('admin.settings.revenue', [
            'period' => $period,
            'periodLabel' => $periodLabel,
            'selectedValue' => $selectedValue,
            'monthOptions' => $monthOptions,
            'yearOptions' => $yearOptions,
            'holdCentsInPeriod' => $holdCentsInPeriod,
            'floatingCentsInPeriod' => $floatingCentsInPeriod,
            'floatingBookingsCount' => $floatingBookingsCount,
            'totalCentsInPeriod' => $holdCentsInPeriod + $floatingCentsInPeriod,
            'monthsInPeriod' => $monthsInPeriod,
            'activeEnrollmentsNow' => $activeEnrollmentsNow,
            'monthlyCentsNow' => $monthlyCentsNow,
            'membersCount' => $membersCount,
            'perCourse' => $perCourse,
        ]);
    }

    public function other()
    {
        return view('admin.settings.other');
    }

    private function monthLabelDa(int $m): string
    {
        $names = [1=>'Januar',2=>'Februar',3=>'Marts',4=>'April',5=>'Maj',6=>'Juni',7=>'Juli',8=>'August',9=>'September',10=>'Oktober',11=>'November',12=>'December'];
        return $names[$m] ?? '';
    }
}
