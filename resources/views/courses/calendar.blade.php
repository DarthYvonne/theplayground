@extends('layouts.app')
@section('content')

@push('styles')
<style>
  /* Widen the calendar beyond the default 720px content cap. */
  .main > * { max-width: 1400px; }

  :root {
    --cal-hour-px: 56px;
    --cal-gutter: 56px;
    --cal-start-hour: 8;
    --cal-end-hour: 22;
  }

  .cal-wrap { background: #fff; border-radius: 12px; box-shadow: 0 1px 2px rgba(0,0,0,0.08); padding: 0; overflow: hidden; }

  .cal-head { display: grid; grid-template-columns: var(--cal-gutter) repeat(5, minmax(0, 1fr)); border-bottom: 1px solid #f0f2f5; background: #fafbfc; }
  .cal-head .gutter { border-right: 1px solid #f0f2f5; }
  .cal-head .day-label { padding: 10px 8px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.3px; color: var(--muted); text-align: center; border-right: 1px solid #f0f2f5; display: flex; flex-direction: column; gap: 2px; }
  .cal-head .day-label:last-child { border-right: none; }
  .cal-head .day-label.today { background: var(--accent-soft); color: var(--accent); }
  .cal-head .day-label .dnum { font-size: 16px; font-weight: 700; letter-spacing: 0; text-transform: none; color: var(--text); }
  .cal-head .day-label.today .dnum { color: var(--accent); }

  /* Optional "no-time" pills row, sits between header and time grid */
  .cal-notime { display: grid; grid-template-columns: var(--cal-gutter) repeat(5, minmax(0, 1fr)); border-bottom: 1px solid #f0f2f5; background: #fafbfc; min-height: 0; }
  .cal-notime .gutter { border-right: 1px solid #f0f2f5; font-size: 10px; color: var(--muted); padding: 6px 4px; text-align: right; }
  .cal-notime .cell { padding: 6px 6px; display: flex; flex-direction: column; gap: 4px; border-right: 1px solid #f0f2f5; }
  .cal-notime .cell:last-child { border-right: none; }
  .cal-notime.empty { display: none; }

  .cal-grid { display: grid; grid-template-columns: var(--cal-gutter) repeat(5, minmax(0, 1fr)); position: relative; }

  .cal-hours { display: flex; flex-direction: column; border-right: 1px solid #f0f2f5; }
  .cal-hours .h { height: var(--cal-hour-px); font-size: 11px; color: var(--muted); padding: 4px 6px 0 0; text-align: right; position: relative; }
  .cal-hours .h::after { content: ''; position: absolute; top: 0; right: -1px; width: 6px; height: 1px; background: #f0f2f5; }

  .cal-col { position: relative; border-right: 1px solid #f0f2f5;
             background-image: linear-gradient(to bottom, transparent calc(var(--cal-hour-px) - 1px), #f0f2f5 calc(var(--cal-hour-px) - 1px), #f0f2f5 var(--cal-hour-px));
             background-size: 100% var(--cal-hour-px); }
  .cal-col:last-child { border-right: none; }
  .cal-col.today { background-color: rgba(24,119,242,0.03); }

  .cal-event { position: absolute; left: 4px; right: 4px; background: #f5f7fa; color: var(--text); border-radius: 8px; padding: 6px 8px; border: 2px solid transparent; overflow: hidden; transition: background 0.1s, border-color 0.1s; display: flex; flex-direction: column; gap: 2px; }
  .cal-event:hover { background: #eaeef3; z-index: 5; }
  .cal-event.cancelled { background: #f5f7fa; border-color: transparent; }
  .cal-event.cancelled .t { text-decoration: line-through; color: var(--muted); }
  .cal-event .t { font-weight: 700; font-size: 12px; line-height: 1.2; word-break: break-word; overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
  .cal-event .tm { color: var(--muted); font-size: 10px; line-height: 1.1; }
  .cal-event .aflyst-badge { font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.4px; color: #c0392b; background: rgba(192,57,43,0.1); border-radius: 3px; padding: 1px 5px; display: inline-block; width: fit-content; }

  /* "Tilmeldt" pill, matching the hold cards. */
  .cal-enrolled-badge { display: inline-flex; align-items: center; gap: 4px; background: #16a34a; color: #fff; font-size: 10px; font-weight: 700; padding: 2px 7px; border-radius: 999px; box-shadow: 0 1px 3px rgba(0,0,0,0.15); width: fit-content; line-height: 1.3; }
  .cal-enrolled-badge i { font-size: 9px; }

  .cal-event.notime { position: static; left: auto; right: auto; padding: 4px 8px; }
  .cal-event.notime .t { -webkit-line-clamp: 1; }

  .cal-unscheduled { margin-top: 18px; background: #fff; border-radius: 12px; box-shadow: 0 1px 2px rgba(0,0,0,0.08); padding: 14px 16px; }
  .cal-unscheduled h3 { font-size: 13px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: 0.3px; margin-bottom: 8px; }
  .cal-unscheduled-list { display: flex; flex-wrap: wrap; gap: 6px; }

  @media (max-width: 900px) {
    /* Mobile: ditch the absolute grid, fall back to per-day stacked lists. */
    .cal-head, .cal-notime, .cal-grid { display: none; }
    .cal-mobile { display: block; }
  }
  @media (min-width: 901px) {
    .cal-mobile { display: none; }
  }

  .cal-mobile-day { background: #fff; border-radius: 12px; box-shadow: 0 1px 2px rgba(0,0,0,0.08); padding: 12px 14px; margin-bottom: 10px; }
  .cal-mobile-day h3 { font-size: 13px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: 0.3px; margin-bottom: 8px; }
  .cal-mobile-day h3.today { color: var(--accent); }
  .cal-mobile-day .row { display: flex; gap: 10px; align-items: center; padding: 8px 0; border-top: 1px solid #f0f2f5; }
  .cal-mobile-day .row:first-of-type { border-top: none; }
  .cal-mobile-day .row.cancelled .ti { text-decoration: line-through; color: var(--muted); }
  .cal-mobile-day .tm { color: var(--muted); font-size: 12px; min-width: 60px; }
  .cal-mobile-day .ti { font-weight: 600; flex: 1; }
  .cal-mobile-day .aflyst-tag { font-size: 9px; font-weight: 700; text-transform: uppercase; color: #c0392b; background: rgba(192,57,43,0.1); border-radius: 3px; padding: 1px 5px; }
  .cal-mobile-day .empty { color: var(--muted); font-size: 12px; font-style: italic; }
</style>
@endpush

<div class="view-header">
  <h1><i class="fa-solid fa-dumbbell" style="color:var(--text);margin-right:8px;"></i>Hold</h1>
  @include('partials.header-actions')
</div>

@include('courses._subnav')

@include('partials.calendar-header', ['view' => $view, 'monday' => $monday, 'monthAnchor' => $monthAnchor, 'routeName' => 'home.calendar'])

@php
  use App\Support\CalendarWeek;

  $enrolledSet = array_flip($enrolledIds ?? []);
  $weekdayLabels = ['mon' => 'Mandag', 'tue' => 'Tirsdag', 'wed' => 'Onsdag', 'thu' => 'Torsdag', 'fri' => 'Fredag'];
  $weekdayDates = $view === 'week' ? CalendarWeek::weekdayDates($monday) : [];
@endphp

@if ($view === 'month')
  @include('partials.calendar-month', [
    'monthAnchor' => $monthAnchor,
    'byDay' => $byDay,
    'cancelledMap' => $cancelledMap,
    'routeName' => 'home.calendar',
    'enrolledSet' => $enrolledSet,
  ])
@else
  @php
    $startHour = 8;
    $endHour = 22;
    $hourPx = 56;

    $parseTime = function ($t) {
        if (!$t) return null;
        $parts = explode(':', (string) $t);
        $h = (int) ($parts[0] ?? 0);
        $m = (int) ($parts[1] ?? 0);
        return $h * 60 + $m;
    };

    $timed = [];
    $untimed = [];
    foreach ($weekdayLabels as $key => $_) {
        $timed[$key] = [];
        $untimed[$key] = [];
        $dateStr = $weekdayDates[$key]->toDateString();
        foreach ($byDay[$key] ?? [] as $c) {
            $cancelled = isset($cancelledMap[$c->id . ':' . $dateStr]);
            $startMin = $parseTime($c->start_time);
            if ($startMin === null) {
                $untimed[$key][] = ['course' => $c, 'cancelled' => $cancelled, 'date' => $dateStr];
                continue;
            }
            $endMin = $parseTime($c->end_time) ?? ($startMin + 60);
            if ($endMin <= $startMin) $endMin = $startMin + 60;
            $visibleStart = max($startMin, $startHour * 60);
            $visibleEnd = min($endMin, $endHour * 60);
            if ($visibleEnd <= $visibleStart) continue;
            $top = ($visibleStart - $startHour * 60) / 60 * $hourPx;
            $height = max(($visibleEnd - $visibleStart) / 60 * $hourPx, 28);
            $timed[$key][] = ['course' => $c, 'top' => $top, 'height' => $height, 'cancelled' => $cancelled, 'date' => $dateStr];
        }
    }
    $hasUntimed = collect($untimed)->flatten(1)->isNotEmpty();
  @endphp

<div class="cal-wrap">
  <div class="cal-head">
    <div class="gutter"></div>
    @foreach ($weekdayLabels as $key => $label)
      @php $d = $weekdayDates[$key]; $isToday = $d->isToday(); @endphp
      <div class="day-label {{ $isToday ? 'today' : '' }}">
        <div>{{ $label }}</div>
        <div class="dnum">{{ $d->day }}/{{ $d->month }}</div>
      </div>
    @endforeach
  </div>

  <div class="cal-notime {{ $hasUntimed ? '' : 'empty' }}">
    <div class="gutter">Uden tid</div>
    @foreach ($weekdayLabels as $key => $_)
      <div class="cell">
        @foreach ($untimed[$key] as $ev)
          @php $c = $ev['course']; @endphp
          <a href="{{ route('courses.show', $c) }}" class="cal-event notime {{ $ev['cancelled'] ? 'cancelled' : '' }}">
            <div class="t">{{ $c->title }}</div>
            @if (isset($enrolledSet[$c->id]))<span class="cal-enrolled-badge"><i class="fa-solid fa-circle-check"></i> Tilmeldt</span>@endif
            @if ($ev['cancelled'])<div class="aflyst-badge">Aflyst</div>@endif
          </a>
        @endforeach
      </div>
    @endforeach
  </div>

  <div class="cal-grid">
    <div class="cal-hours">
      @for ($h = $startHour; $h < $endHour; $h++)
        <div class="h">{{ sprintf('%02d:00', $h) }}</div>
      @endfor
    </div>
    @foreach ($weekdayLabels as $key => $_)
      @php $isToday = $weekdayDates[$key]->isToday(); @endphp
      <div class="cal-col {{ $isToday ? 'today' : '' }}" style="height: {{ ($endHour - $startHour) * $hourPx }}px;">
        @foreach ($timed[$key] as $ev)
          @php $c = $ev['course']; @endphp
          <a href="{{ route('courses.show', $c) }}"
             class="cal-event {{ $ev['cancelled'] ? 'cancelled' : '' }}"
             style="top: {{ $ev['top'] }}px; height: {{ $ev['height'] }}px;">
            <div class="t">{{ $c->title }}</div>
            @if ($c->timeRange())<div class="tm">{{ $c->timeRange() }}</div>@endif
            @if (isset($enrolledSet[$c->id]))<span class="cal-enrolled-badge"><i class="fa-solid fa-circle-check"></i> Tilmeldt</span>@endif
            @if ($ev['cancelled'])<div class="aflyst-badge">Aflyst</div>@endif
          </a>
        @endforeach
      </div>
    @endforeach
  </div>
</div>

{{-- Mobile fallback: per-day stacked list, time on the left. --}}
<div class="cal-mobile">
  @foreach ($weekdayLabels as $key => $label)
    @php $d = $weekdayDates[$key]; @endphp
    <div class="cal-mobile-day">
      <h3 class="{{ $d->isToday() ? 'today' : '' }}">{{ $label }} {{ $d->day }}/{{ $d->month }}</h3>
      @php
        $dayEvents = collect($timed[$key])->concat($untimed[$key]);
      @endphp
      @forelse ($dayEvents as $ev)
        @php $c = $ev['course']; @endphp
        <a href="{{ route('courses.show', $c) }}" class="row {{ $ev['cancelled'] ? 'cancelled' : '' }}" style="color:inherit;">
          <div class="tm">{{ $c->timeRange() ?? '—' }}</div>
          <div class="ti">{{ $c->title }}</div>
          @if ($ev['cancelled'])<span class="aflyst-tag">Aflyst</span>@endif
          @if (isset($enrolledSet[$c->id]))<span class="cal-enrolled-badge"><i class="fa-solid fa-circle-check"></i> Tilmeldt</span>@endif
        </a>
      @empty
        <div class="empty">Ingen hold</div>
      @endforelse
    </div>
  @endforeach
</div>
@endif

@if ($weekendCourses->isNotEmpty())
  <div class="cal-unscheduled">
    <h3>Weekend</h3>
    <div class="cal-unscheduled-list">
      @foreach ($weekendCourses as $c)
        <a href="{{ route('courses.show', $c) }}" class="tag muted" style="padding:6px 12px;">{{ $c->title }}</a>
      @endforeach
    </div>
  </div>
@endif

@if ($unscheduled->isNotEmpty())
  <div class="cal-unscheduled">
    <h3>Uden fast skema</h3>
    <div class="cal-unscheduled-list">
      @foreach ($unscheduled as $c)
        <a href="{{ route('courses.show', $c) }}" class="tag muted" style="padding:6px 12px;">{{ $c->title }}</a>
      @endforeach
    </div>
  </div>
@endif

@endsection
