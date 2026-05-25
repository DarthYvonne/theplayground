@extends('layouts.app')
@section('content')

@push('styles')
<style>
  .period-bar { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin-bottom: 16px; background: #fff; border-radius: 12px; box-shadow: 0 1px 2px rgba(0,0,0,0.08); padding: 12px 14px; }
  .period-bar .seg { display: inline-flex; background: #f0f2f5; border-radius: 8px; padding: 3px; }
  .period-bar .seg .period-btn { background: transparent; border: 0; padding: 6px 14px; border-radius: 6px; font-weight: 600; font-size: 13px; color: var(--muted); cursor: pointer; text-decoration: none; display: inline-block; }
  .period-bar .seg .period-btn.active { background: #fff; color: var(--text); box-shadow: 0 1px 2px rgba(0,0,0,0.08); }
  .period-bar select { border: 1px solid var(--border); border-radius: 8px; padding: 7px 10px; font-size: 14px; background: #fff; }
  .period-bar .nudge { margin-left: auto; color: var(--muted); font-size: 13px; }

  .stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; margin-bottom: 18px; }
  .stat { background: #fff; border-radius: 12px; box-shadow: 0 1px 2px rgba(0,0,0,0.08); padding: 16px 18px; }
  .stat .label { color: var(--muted); font-size: 12px; text-transform: uppercase; letter-spacing: 0.4px; font-weight: 700; }
  .stat .value { font-size: 24px; font-weight: 700; margin-top: 6px; }
  .stat .value small { font-size: 13px; color: var(--muted); font-weight: 500; margin-left: 4px; }
  .stat.total .value { color: var(--accent); }

  .rev-section-title { font-size: 13px; text-transform: uppercase; letter-spacing: 0.4px; color: var(--muted); font-weight: 700; margin: 22px 4px 8px; }

  .rev-table { background: #fff; border-radius: 12px; box-shadow: 0 1px 2px rgba(0,0,0,0.08); overflow: hidden; }
  .rev-table .row { display: grid; grid-template-columns: 1fr 110px 110px 160px; gap: 12px; align-items: center; padding: 12px 16px; border-top: 1px solid #f0f2f5; }
  .rev-table .row:first-child { border-top: none; background: #fafbfc; font-size: 12px; text-transform: uppercase; letter-spacing: 0.3px; color: var(--muted); font-weight: 700; padding: 10px 16px; }
  .rev-table .num { text-align: right; font-variant-numeric: tabular-nums; }
  .rev-table .t { font-weight: 600; }
  .rev-table .sub { color: var(--muted); font-size: 12px; margin-top: 2px; }
  .rev-empty { color: var(--muted); padding: 18px; text-align: center; }
  @media (max-width: 767px) {
    .rev-table .row { grid-template-columns: 1fr 110px; }
    .rev-table .col-hide-mobile { display: none; }
  }
</style>
@endpush

<div class="view-header">
  <h1>Indstillinger</h1>
  @include('partials.header-actions')
</div>

@include('admin.settings._subnav')

@php
  $fmtKr = function (int $cents) {
      $amt = number_format($cents / 100, $cents % 100 === 0 ? 0 : 2, ',', '.');
      return $amt . ' kr';
  };
@endphp

<div class="period-bar">
  <div class="seg" role="tablist">
    <a href="{{ route('admin.settings.revenue', ['period' => 'month']) }}" class="period-btn {{ $period === 'month' ? 'active' : '' }}">Måned</a>
    <a href="{{ route('admin.settings.revenue', ['period' => 'year']) }}" class="period-btn {{ $period === 'year' ? 'active' : '' }}">År</a>
  </div>
  <form method="GET" action="{{ route('admin.settings.revenue') }}" id="period-form" style="display:flex;gap:10px;align-items:center;">
    <input type="hidden" name="period" value="{{ $period }}">
    <select name="date" onchange="document.getElementById('period-form').submit()">
      @if ($period === 'year')
        @foreach ($yearOptions as $opt)
          <option value="{{ $opt['value'] }}" {{ $opt['value'] === $selectedValue ? 'selected' : '' }}>{{ $opt['label'] }}</option>
        @endforeach
      @else
        @foreach ($monthOptions as $opt)
          <option value="{{ $opt['value'] }}" {{ $opt['value'] === $selectedValue ? 'selected' : '' }}>{{ $opt['label'] }}</option>
        @endforeach
      @endif
    </select>
  </form>
  <div class="nudge">Viser: <strong>{{ $periodLabel }}</strong></div>
</div>

<div class="stat-grid">
  <div class="stat">
    <div class="label">Hold ({{ $period === 'year' ? 'året' : 'måneden' }})</div>
    <div class="value">
      {{ $fmtKr($holdCentsInPeriod) }}
    </div>
  </div>
  <div class="stat">
    <div class="label">Floating ({{ $period === 'year' ? 'året' : 'måneden' }})</div>
    <div class="value">
      {{ $fmtKr($floatingCentsInPeriod) }}
      <small>{{ $floatingBookingsCount }} bookinger</small>
    </div>
  </div>
  <div class="stat total">
    <div class="label">Total ({{ $period === 'year' ? 'året' : 'måneden' }})</div>
    <div class="value">
      {{ $fmtKr($totalCentsInPeriod) }}
    </div>
  </div>
  <div class="stat">
    <div class="label">Aktive tilmeldinger nu</div>
    <div class="value">
      {{ $activeEnrollmentsNow }}
      <small>{{ $fmtKr($monthlyCentsNow) }}/md</small>
    </div>
  </div>
</div>

<div class="rev-section-title">Hold – pr. hold i {{ $periodLabel }}</div>
<div class="rev-table">
  <div class="row">
    <div>Hold</div>
    <div class="num col-hide-mobile">Pris/md</div>
    <div class="num col-hide-mobile">Tilmeldte</div>
    <div class="num">Omsætning ({{ $period === 'year' ? 'året' : 'mdr.' }})</div>
  </div>
  @forelse ($perCourse as $c)
    @php $courseRev = (int) $c->price_cents * (int) $c->active_in_period * $monthsInPeriod; @endphp
    <div class="row">
      <div>
        <div class="t">{{ $c->title }}</div>
        <div class="sub">{{ $c->is_active ? 'Aktiv' : 'Kladde' }}</div>
      </div>
      <div class="num col-hide-mobile">{{ $fmtKr((int) $c->price_cents) }}</div>
      <div class="num col-hide-mobile">{{ $c->active_in_period }}</div>
      <div class="num">{{ $fmtKr($courseRev) }}</div>
    </div>
  @empty
    <div class="rev-empty">Ingen hold endnu.</div>
  @endforelse
</div>

<div class="rev-section-title">Floating – {{ $periodLabel }}</div>
<div class="rev-table">
  <div class="row">
    <div>Type</div>
    <div class="num col-hide-mobile"></div>
    <div class="num col-hide-mobile">Bookinger</div>
    <div class="num">Omsætning</div>
  </div>
  <div class="row">
    <div>
      <div class="t">Floating-bookinger</div>
      <div class="sub">Betalte slots i perioden</div>
    </div>
    <div class="num col-hide-mobile"></div>
    <div class="num col-hide-mobile">{{ $floatingBookingsCount }}</div>
    <div class="num">{{ $fmtKr($floatingCentsInPeriod) }}</div>
  </div>
</div>

@endsection
