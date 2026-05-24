@extends('layouts.app')
@section('content')

@push('styles')
<style>
  /* Widen the calendar beyond the default 720px content cap so 7 columns
     have room for long course titles. */
  .main > * { max-width: 1400px; }

  .cal-grid { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 10px; }
  .cal-col { background: #fff; border-radius: 12px; box-shadow: 0 1px 2px rgba(0,0,0,0.08); display: flex; flex-direction: column; min-height: 240px; overflow: hidden; }
  .cal-col-head { padding: 10px 12px; background: #fafbfc; border-bottom: 1px solid #f0f2f5; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.3px; color: var(--muted); text-align: center; }
  .cal-col-head.today { background: var(--accent-soft); color: var(--accent); }
  .cal-col-body { padding: 8px; display: flex; flex-direction: column; gap: 6px; flex: 1; }

  .cal-event { display: block; padding: 8px 10px; border-radius: 8px; background: #f5f7fa; color: var(--text); border: 2px solid transparent; transition: background 0.1s, border-color 0.1s; }
  .cal-event:hover { background: #eaeef3; }
  .cal-event .t { font-weight: 700; font-size: 13px; line-height: 1.25; word-break: break-word; }
  .cal-event .time { color: var(--muted); font-size: 11px; margin-top: 2px; }

  /* Joined hold get a solid accent frame so they pop against the catalog. */
  .cal-event.enrolled { background: var(--accent-soft); border-color: var(--accent); }
  .cal-event.enrolled:hover { background: #dbe6fb; }

  .cal-empty { color: var(--muted); font-size: 12px; text-align: center; padding: 16px 4px; font-style: italic; }

  .cal-unscheduled { margin-top: 18px; background: #fff; border-radius: 12px; box-shadow: 0 1px 2px rgba(0,0,0,0.08); padding: 14px 16px; }
  .cal-unscheduled h3 { font-size: 13px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: 0.3px; margin-bottom: 8px; }
  .cal-unscheduled-list { display: flex; flex-wrap: wrap; gap: 6px; }

  @media (max-width: 900px) {
    .cal-grid { grid-template-columns: 1fr; }
    .cal-col { min-height: 0; }
    .cal-col-body { padding-bottom: 12px; }
  }
</style>
@endpush

<div class="view-header">
  <h1>Hold</h1>
  @include('partials.header-actions')
</div>

@include('courses._subnav')

@php
  $today = strtolower(now()->locale('en')->format('D'));
  $enrolledSet = array_flip($enrolledIds ?? []);
@endphp

<div class="cal-grid">
  @foreach (App\Models\Course::WEEKDAYS as $key => $label)
    <div class="cal-col">
      <div class="cal-col-head {{ $key === $today ? 'today' : '' }}">{{ $label }}</div>
      <div class="cal-col-body">
        @forelse ($byDay[$key] as $c)
          <a href="{{ route('courses.show', $c) }}" class="cal-event {{ isset($enrolledSet[$c->id]) ? 'enrolled' : '' }}">
            <div class="t">{{ $c->title }}</div>
            @if ($c->timeRange())<div class="time"><i class="fa-regular fa-clock"></i> {{ $c->timeRange() }}</div>@endif
          </a>
        @empty
          <div class="cal-empty">—</div>
        @endforelse
      </div>
    </div>
  @endforeach
</div>

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
