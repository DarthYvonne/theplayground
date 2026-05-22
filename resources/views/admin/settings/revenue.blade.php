@extends('layouts.app')
@section('content')

@push('styles')
<style>
  .stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; margin-bottom: 18px; }
  .stat { background: #fff; border-radius: 12px; box-shadow: 0 1px 2px rgba(0,0,0,0.08); padding: 16px 18px; }
  .stat .label { color: var(--muted); font-size: 12px; text-transform: uppercase; letter-spacing: 0.4px; font-weight: 700; }
  .stat .value { font-size: 26px; font-weight: 700; margin-top: 6px; }
  .stat .value small { font-size: 14px; color: var(--muted); font-weight: 500; margin-left: 4px; }

  .rev-table { background: #fff; border-radius: 12px; box-shadow: 0 1px 2px rgba(0,0,0,0.08); overflow: hidden; }
  .rev-table .row { display: grid; grid-template-columns: 1fr 110px 110px 140px; gap: 12px; align-items: center; padding: 12px 16px; border-top: 1px solid #f0f2f5; }
  .rev-table .row:first-child { border-top: none; background: #fafbfc; font-size: 12px; text-transform: uppercase; letter-spacing: 0.3px; color: var(--muted); font-weight: 700; padding: 10px 16px; }
  .rev-table .num { text-align: right; font-variant-numeric: tabular-nums; }
  .rev-table .t { font-weight: 600; }
  .rev-table .sub { color: var(--muted); font-size: 12px; margin-top: 2px; }
  .rev-empty { color: var(--muted); padding: 18px; text-align: center; }
  @media (max-width: 767px) {
    .rev-table .row { grid-template-columns: 1fr 90px; }
    .rev-table .col-hide-mobile { display: none; }
  }
</style>
@endpush

<div class="view-header">
  <h1>Indstillinger</h1>
  @include('partials.header-actions')
</div>

@include('admin.settings._subnav')

<div class="stat-grid">
  <div class="stat">
    <div class="label">Månedlig omsætning</div>
    <div class="value">
      {{ number_format($monthlyCents / 100, $monthlyCents % 100 === 0 ? 0 : 2, ',', '.') }}
      <small>{{ $currency }}/md</small>
    </div>
  </div>
  <div class="stat">
    <div class="label">Aktive tilmeldinger</div>
    <div class="value">{{ $activeEnrollments }}</div>
  </div>
  <div class="stat">
    <div class="label">Årligt på nuværende tempo</div>
    <div class="value">
      {{ number_format(($monthlyCents * 12) / 100, 0, ',', '.') }}
      <small>{{ $currency }}/år</small>
    </div>
  </div>
</div>

<div class="rev-table">
  <div class="row">
    <div>Hold</div>
    <div class="num col-hide-mobile">Pris/md</div>
    <div class="num col-hide-mobile">Tilmeldte</div>
    <div class="num">Omsætning/md</div>
  </div>
  @forelse ($perCourse as $c)
    @php $courseMrr = $c->price_cents * $c->active_count; @endphp
    <div class="row">
      <div>
        <div class="t">{{ $c->title }}</div>
        <div class="sub">{{ $c->is_active ? 'Aktiv' : 'Kladde' }}</div>
      </div>
      <div class="num col-hide-mobile">{{ number_format($c->price_cents / 100, $c->price_cents % 100 === 0 ? 0 : 2, ',', '.') }}</div>
      <div class="num col-hide-mobile">{{ $c->active_count }}</div>
      <div class="num">{{ number_format($courseMrr / 100, $courseMrr % 100 === 0 ? 0 : 2, ',', '.') }} {{ $currency }}</div>
    </div>
  @empty
    <div class="rev-empty">Ingen hold endnu.</div>
  @endforelse
</div>

@endsection
