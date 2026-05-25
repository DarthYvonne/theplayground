<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Date helpers for the courses calendar. The calendar is Mon–Fri only; weekend
 * courses still surface in a separate row below the grid.
 */
class CalendarWeek
{
    public const DAY_KEYS = ['mon','tue','wed','thu','fri'];

    /** Carbon::dayOfWeek key (Sunday=0..Saturday=6) → schedule key. */
    private const CARBON_DOW_TO_KEY = [
        1 => 'mon', 2 => 'tue', 3 => 'wed', 4 => 'thu', 5 => 'fri',
        6 => 'sat', 0 => 'sun',
    ];

    private const MONTHS_DA = [
        1 => 'januar', 2 => 'februar', 3 => 'marts', 4 => 'april',
        5 => 'maj', 6 => 'juni', 7 => 'juli', 8 => 'august',
        9 => 'september', 10 => 'oktober', 11 => 'november', 12 => 'december',
    ];

    /**
     * Returns the Monday of the requested week. Accepts:
     *   ?week=YYYY-Www   (ISO week, e.g. 2026-W22)
     *   ?date=YYYY-MM-DD (any date within the desired week)
     * Falls back to current week.
     */
    public static function weekFromRequest(Request $r): Carbon
    {
        $week = (string) $r->query('week', '');
        if (preg_match('/^(\d{4})-W(\d{1,2})$/', $week, $m)) {
            $year = (int) $m[1];
            $isoWeek = (int) $m[2];
            if ($isoWeek >= 1 && $isoWeek <= 53) {
                return Carbon::now()->setISODate($year, $isoWeek, 1)->startOfDay();
            }
        }
        $date = (string) $r->query('date', '');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            try {
                return Carbon::parse($date)->startOfWeek(Carbon::MONDAY);
            } catch (\Throwable) {}
        }
        return Carbon::now()->startOfWeek(Carbon::MONDAY);
    }

    /** Returns [Monday, Friday] for a given Monday. Calendar is Mon–Fri only. */
    public static function weekRange(Carbon $monday): array
    {
        return [$monday->copy()->startOfDay(), $monday->copy()->addDays(4)->endOfDay()];
    }

    /** Date range covering the month grid: from Monday of week containing the 1st to Friday of week containing the last day. */
    public static function monthGridRange(Carbon $firstOfMonth): array
    {
        $start = $firstOfMonth->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY);
        $end = $firstOfMonth->copy()->endOfMonth()->startOfWeek(Carbon::MONDAY)->addDays(4)->endOfDay();
        return [$start, $end];
    }

    /**
     * Returns the first day of the requested month. Accepts ?month=YYYY-MM.
     */
    public static function monthFromRequest(Request $r): Carbon
    {
        $m = (string) $r->query('month', '');
        if (preg_match('/^(\d{4})-(\d{1,2})$/', $m, $parts)) {
            $year = (int) $parts[1];
            $month = (int) $parts[2];
            if ($month >= 1 && $month <= 12) {
                return Carbon::create($year, $month, 1)->startOfDay();
            }
        }
        return Carbon::now()->startOfMonth();
    }

    /** Schedule weekday key ('mon'..'sun') for a date. */
    public static function dateKey(Carbon $d): string
    {
        return self::CARBON_DOW_TO_KEY[$d->dayOfWeek] ?? 'mon';
    }

    public static function weekParam(Carbon $monday): string
    {
        return sprintf('%04d-W%02d', $monday->isoWeekYear, $monday->isoWeek);
    }

    public static function monthParam(Carbon $anyDayInMonth): string
    {
        return $anyDayInMonth->format('Y-m');
    }

    /** "Uge 22 · 25.–29. maj 2026" */
    public static function weekLabel(Carbon $monday): string
    {
        [$start, $end] = self::weekRange($monday);
        $isoWeek = $monday->isoWeek;
        $startMonth = self::MONTHS_DA[(int) $start->month];
        $endMonth = self::MONTHS_DA[(int) $end->month];
        if ($start->month === $end->month && $start->year === $end->year) {
            return sprintf('Uge %d · %d.–%d. %s %d', $isoWeek, $start->day, $end->day, $startMonth, $start->year);
        }
        if ($start->year === $end->year) {
            return sprintf('Uge %d · %d. %s – %d. %s %d', $isoWeek, $start->day, $startMonth, $end->day, $endMonth, $start->year);
        }
        return sprintf('Uge %d · %d. %s %d – %d. %s %d', $isoWeek, $start->day, $startMonth, $start->year, $end->day, $endMonth, $end->year);
    }

    /** "Maj 2026" */
    public static function monthLabel(Carbon $anyDayInMonth): string
    {
        $name = self::MONTHS_DA[(int) $anyDayInMonth->month];
        return ucfirst($name) . ' ' . $anyDayInMonth->year;
    }

    /** "Mandag 25/5" */
    public static function dayLabel(Carbon $d): string
    {
        $names = ['mon' => 'Mandag', 'tue' => 'Tirsdag', 'wed' => 'Onsdag', 'thu' => 'Torsdag', 'fri' => 'Fredag', 'sat' => 'Lørdag', 'sun' => 'Søndag'];
        $key = self::dateKey($d);
        return ($names[$key] ?? '') . ' ' . $d->day . '/' . $d->month;
    }

    /** Maps Mon–Fri dates of a week keyed by schedule key. */
    public static function weekdayDates(Carbon $monday): array
    {
        $out = [];
        foreach (self::DAY_KEYS as $i => $key) {
            $out[$key] = $monday->copy()->addDays($i)->startOfDay();
        }
        return $out;
    }

    /**
     * Resolve the calendar context for a request: week anchor, month anchor,
     * the visible date range for the selected view, and which view is active.
     *
     * @return array{view:string, monday:Carbon, monthAnchor:Carbon, rangeStart:Carbon, rangeEnd:Carbon}
     */
    public static function resolveContext(Request $r): array
    {
        $view = $r->query('view') === 'month' ? 'month' : 'week';
        $monday = self::weekFromRequest($r);
        $monthAnchor = self::monthFromRequest($r);
        if ($view === 'month') {
            [$rangeStart, $rangeEnd] = self::monthGridRange($monthAnchor);
        } else {
            [$rangeStart, $rangeEnd] = self::weekRange($monday);
        }
        return compact('view', 'monday', 'monthAnchor', 'rangeStart', 'rangeEnd');
    }
}
