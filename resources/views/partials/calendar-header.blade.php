{{-- Shared calendar navigation: week/month label, prev/next/today, view toggle.
     Props:
       $view        - 'week' | 'month'
       $monday      - Carbon (Monday of currently shown week)
       $monthAnchor - Carbon (first day of currently shown month)
       $routeName   - Laravel route name for the calendar page (built with route())
--}}
@push('styles')
<style>
  .cal-nav { display: flex; align-items: center; justify-content: space-between; gap: 12px;
             background: #fff; border-radius: 12px; box-shadow: 0 1px 2px rgba(0,0,0,0.08);
             padding: 10px 14px; margin-bottom: 12px; flex-wrap: wrap; }
  .cal-nav .group { display: flex; align-items: center; gap: 6px; }
  .cal-nav .label { font-weight: 700; font-size: 15px; color: var(--text); }
  .cal-nav .label .uge { color: var(--accent); margin-right: 6px; }
  .cal-nav-btn { display: inline-flex; align-items: center; gap: 6px; padding: 6px 10px;
                 background: #f5f7fa; color: var(--text); border-radius: 8px; font-size: 13px;
                 font-weight: 600; border: 1px solid transparent; transition: background 0.1s; }
  .cal-nav-btn:hover { background: #eaeef3; }
  .cal-nav-btn.icon { padding: 6px 10px; }
  .cal-nav-toggle { display: inline-flex; background: #f5f7fa; border-radius: 8px; padding: 2px; }
  .cal-nav-toggle a { padding: 4px 12px; font-size: 13px; font-weight: 600; color: var(--muted); border-radius: 6px; }
  .cal-nav-toggle a.active { background: #fff; color: var(--text); box-shadow: 0 1px 2px rgba(0,0,0,0.08); }
  @media (max-width: 640px) {
    .cal-nav { flex-direction: column; align-items: stretch; }
    .cal-nav .group { justify-content: center; }
    .cal-nav .label { text-align: center; }
  }
</style>
@endpush

@php
  use App\Support\CalendarWeek;

  $isMonth = ($view ?? 'week') === 'month';
  $todayMonday = \Carbon\Carbon::now()->startOfWeek(\Carbon\Carbon::MONDAY);
  $todayMonth = \Carbon\Carbon::now()->startOfMonth();

  if ($isMonth) {
      $prevAnchor = $monthAnchor->copy()->subMonth()->startOfMonth();
      $nextAnchor = $monthAnchor->copy()->addMonth()->startOfMonth();
      $prevParams = ['month' => CalendarWeek::monthParam($prevAnchor), 'view' => 'month'];
      $nextParams = ['month' => CalendarWeek::monthParam($nextAnchor), 'view' => 'month'];
      $todayParams = ['month' => CalendarWeek::monthParam($todayMonth), 'view' => 'month'];
      $centerLabel = CalendarWeek::monthLabel($monthAnchor);
      $prevLabel = 'Forrige måned';
      $nextLabel = 'Næste måned';
  } else {
      $prevMonday = $monday->copy()->subWeek();
      $nextMonday = $monday->copy()->addWeek();
      $prevParams = ['week' => CalendarWeek::weekParam($prevMonday)];
      $nextParams = ['week' => CalendarWeek::weekParam($nextMonday)];
      $todayParams = ['week' => CalendarWeek::weekParam($todayMonday)];
      $centerLabel = CalendarWeek::weekLabel($monday);
      $prevLabel = 'Forrige uge';
      $nextLabel = 'Næste uge';
  }

  $weekToggleParams = ['view' => 'week', 'week' => CalendarWeek::weekParam($monday)];
  $monthToggleParams = ['view' => 'month', 'month' => CalendarWeek::monthParam($monthAnchor)];
@endphp

<div class="cal-nav">
  <div class="group">
    <a href="{{ route($routeName, $prevParams) }}" class="cal-nav-btn icon" title="{{ $prevLabel }}" aria-label="{{ $prevLabel }}">
      <i class="fa-solid fa-chevron-left"></i>
    </a>
    <a href="{{ route($routeName, $todayParams) }}" class="cal-nav-btn">I dag</a>
    <a href="{{ route($routeName, $nextParams) }}" class="cal-nav-btn icon" title="{{ $nextLabel }}" aria-label="{{ $nextLabel }}">
      <i class="fa-solid fa-chevron-right"></i>
    </a>
  </div>

  <div class="label">
    @if ($isMonth)
      {{ $centerLabel }}
    @else
      @php
        $parts = explode(' · ', $centerLabel, 2);
      @endphp
      <span class="uge">{{ $parts[0] }}</span><span style="color:var(--muted);">{{ isset($parts[1]) ? '· ' . $parts[1] : '' }}</span>
    @endif
  </div>

  <div class="cal-nav-toggle" role="tablist">
    <a href="{{ route($routeName, $weekToggleParams) }}" class="{{ $isMonth ? '' : 'active' }}">Uge</a>
    <a href="{{ route($routeName, $monthToggleParams) }}" class="{{ $isMonth ? 'active' : '' }}">Måned</a>
  </div>
</div>
