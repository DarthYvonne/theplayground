@extends('layouts.app')
@section('content')

@push('styles')
<style>
  a.course-tile { color: inherit; text-decoration: none; transition: transform 0.1s, box-shadow 0.1s; }
  a.course-tile:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(0,0,0,0.08); }
  .course-tile-title { font-size: 18px; line-height: 1.25; }
  .course-tile-sched { color: var(--muted); font-size: 13px; margin-top: 8px; display: flex; align-items: center; gap: 6px; }
  .course-tile-trainer { color: var(--muted); font-size: 13px; margin-top: 4px; }
  .ft-intro { color: var(--muted); font-size: 14px; line-height: 1.5; margin-bottom: 18px; max-width: 640px; }
  .empty-card { padding: 28px 20px; text-align: center; color: var(--muted); }
</style>
@endpush

<div class="view-header">
  <h1><i class="fa-solid fa-people-group" style="color:var(--text);margin-right:8px;"></i>Fællestræning</h1>
  @include('partials.header-actions')
</div>

@php $covered = auth()->check() && auth()->user()->hasPaidMembership(); @endphp
<p class="ft-intro">
  @if ($covered)
    Fællestræning er inkluderet i dit medlemskab. Du er automatisk med &mdash; mød bare op.
  @else
    Fællestræning er gratis, når du har et løbende medlemskab på et hold eller personlig træning.
    Du behøver ikke tilmelde dig &mdash; du møder bare op.
  @endif
</p>

@if ($courses->isEmpty())
  <div class="card">
    <div class="empty-card">Der er ingen fællestræning lige nu.</div>
  </div>
@else
  <div class="course-grid">
    @foreach ($courses as $course)
      <a href="{{ route('courses.show', $course) }}" class="card course-tile" aria-label="{{ $course->title }}">
        <div class="img-wrap">
          @include('partials.course-hero-thumb', ['course' => $course])
        </div>
        <div class="card-pad">
          <div class="course-tile-title">{{ $course->title }}</div>
          @if ($course->scheduleLabel())
            <div class="course-tile-sched"><i class="fa-regular fa-clock"></i>{{ $course->scheduleLabel() }}</div>
          @endif
          <div class="course-tile-trainer"><i class="fa-regular fa-user" style="margin-right:4px;"></i>{{ $course->trainerNames() }}</div>
        </div>
      </a>
    @endforeach
  </div>
@endif

@endsection
