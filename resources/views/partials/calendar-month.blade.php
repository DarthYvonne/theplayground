{{-- Month grid view (Mon–Fri × N rows). Read-only — clicking a day jumps to
     the week view for that week so the trainer can cancel from there.
     Props:
       $monthAnchor   - Carbon (first day of month shown)
       $byDay         - array<dayKey, Course[]> active courses keyed by mon..fri
       $cancelledMap  - array<string, CourseCancellation>
       $routeName     - calendar route name (used to link day → week view)
       $enrolledSet   - array<int,bool> of course ids the user is enrolled in (optional)
--}}
@push('styles')
<style>
  .cal-month { background: #fff; border-radius: 12px; box-shadow: 0 1px 2px rgba(0,0,0,0.08); overflow: hidden; }
  .cal-month-head { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); border-bottom: 1px solid #f0f2f5; background: #fafbfc; }
  .cal-month-head .d { padding: 10px 12px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.3px; color: var(--muted); text-align: center; border-right: 1px solid #f0f2f5; }
  .cal-month-head .d:last-child { border-right: none; }
  .cal-month-grid { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); grid-auto-rows: minmax(110px, auto); }
  .cal-month-cell { border-right: 1px solid #f0f2f5; border-bottom: 1px solid #f0f2f5; padding: 6px 8px; display: flex; flex-direction: column; gap: 4px; min-height: 110px; position: relative; }
  .cal-month-cell:nth-child(5n) { border-right: none; }
  .cal-month-cell.out { background: #fafbfc; }
  .cal-month-cell.out .day-num { color: #cbd0d6; }
  .cal-month-cell.today .day-num { background: var(--accent); color: #fff; }
  .cal-month-cell .day-num { display: inline-flex; align-items: center; justify-content: center; min-width: 22px; height: 22px; padding: 0 6px; border-radius: 11px; font-size: 12px; font-weight: 700; color: var(--text); align-self: flex-start; }
  .cal-month-cell .day-num:hover { background: #f5f7fa; }
  .cal-month-cell.today .day-num:hover { background: var(--accent); }
  .cal-month-cell .chip { display: flex; gap: 6px; align-items: center; font-size: 11px; line-height: 1.3; padding: 2px 6px; border-radius: 6px; background: var(--accent-soft); color: var(--text); overflow: hidden; }
  .cal-month-cell .chip .ti { font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; flex: 1; }
  .cal-month-cell .chip .tm { color: var(--muted); font-size: 10px; flex-shrink: 0; }
  .cal-month-cell .chip .enrolled-mark { color: #16a34a; font-size: 11px; flex-shrink: 0; }
  .cal-month-cell .chip.cancelled { background: #f5f7fa; }
  .cal-month-cell .chip.cancelled .ti { text-decoration: line-through; color: var(--muted); }
  .cal-month-cell .more { font-size: 11px; color: var(--muted); padding: 2px 6px; }
  .cal-month-cell .more:hover { color: var(--accent); }
</style>
@endpush

@php
  use App\Support\CalendarWeek;

  $start = $monthAnchor->copy()->startOfMonth()->startOfWeek(\Carbon\Carbon::MONDAY);
  $endOfGrid = $monthAnchor->copy()->endOfMonth()->startOfWeek(\Carbon\Carbon::MONDAY)->addDays(4);
  $today = \Carbon\Carbon::now()->startOfDay();
  $enrolledSet = $enrolledSet ?? [];
@endphp

<div class="cal-month">
  <div class="cal-month-head">
    <div class="d">Mandag</div><div class="d">Tirsdag</div><div class="d">Onsdag</div><div class="d">Torsdag</div><div class="d">Fredag</div>
  </div>
  <div class="cal-month-grid">
    @php $cursor = $start->copy(); @endphp
    @while ($cursor->lte($endOfGrid))
      @php
        $dow = $cursor->dayOfWeek; // 1..7 ISO via Carbon mon-fri
        if ($dow < 1 || $dow > 5) { $cursor->addDay(); continue; }
        $key = CalendarWeek::dateKey($cursor);
        $dateStr = $cursor->toDateString();
        $inMonth = $cursor->month === $monthAnchor->month;
        $isToday = $cursor->isSameDay($today);
        $events = $byDay[$key] ?? [];
        $visible = array_slice($events, 0, 3);
        $hidden = max(0, count($events) - count($visible));
        $weekLink = route($routeName, ['week' => CalendarWeek::weekParam($cursor->copy()->startOfWeek(\Carbon\Carbon::MONDAY)), 'view' => 'week']);
      @endphp
      <div class="cal-month-cell {{ $inMonth ? '' : 'out' }} {{ $isToday ? 'today' : '' }}">
        <a href="{{ $weekLink }}" class="day-num">{{ $cursor->day }}</a>
        @foreach ($visible as $c)
          @php
            $cancelled = isset($cancelledMap[$c->id . ':' . $dateStr]);
            $enrolled = isset($enrolledSet[$c->id]);
          @endphp
          <a href="{{ $weekLink }}" class="chip {{ $cancelled ? 'cancelled' : '' }}" title="{{ $c->title }}{{ $enrolled ? ' (tilmeldt)' : '' }}{{ $cancelled ? ' (aflyst)' : '' }}">
            @if ($enrolled)<i class="fa-solid fa-circle-check enrolled-mark" aria-label="Tilmeldt"></i>@endif
            <span class="ti">{{ $c->title }}</span>
            @if ($c->start_time)<span class="tm">{{ substr($c->start_time, 0, 5) }}</span>@endif
          </a>
        @endforeach
        @if ($hidden > 0)
          <a href="{{ $weekLink }}" class="more">+{{ $hidden }} flere</a>
        @endif
      </div>
      @php $cursor->addDay(); @endphp
    @endwhile
  </div>
</div>
