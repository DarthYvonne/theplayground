@extends('layouts.app')
@section('content')

@push('styles')
<style>
  a.course-tile { color: inherit; text-decoration: none; transition: transform 0.1s, box-shadow 0.1s; }
  a.course-tile:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(0,0,0,0.08); }
  .course-tile .img-wrap { position: relative; }
  .course-tile-title { font-size: 18px; line-height: 1.25; }
  .course-tile-sched { color: var(--muted); font-size: 13px; margin-top: 8px; display: flex; align-items: center; gap: 6px; }
  .course-tile-trainer { color: var(--muted); font-size: 13px; margin-top: 4px; }
  .course-tile-enrolled-badge { position: absolute; top: 10px; right: 10px; background: #16a34a; color: #fff; font-size: 12px; font-weight: 700; padding: 4px 10px; border-radius: 999px; box-shadow: 0 2px 6px rgba(0,0,0,0.18); display: inline-flex; align-items: center; gap: 5px; }
  .empty-card { padding: 28px 20px; text-align: center; color: var(--muted); }
  .empty-card a { color: var(--accent); font-weight: 600; }
</style>
@endpush

<div class="view-header">
  <h1><i class="fa-solid fa-dumbbell" style="color:var(--text);margin-right:8px;"></i>Hold</h1>
  @include('partials.header-actions')
</div>

@include('courses._subnav')

@if ($enrolledCourses->isEmpty())
  <div class="card">
    <div class="empty-card">Du er ikke tilmeldt noget endnu. <a href="{{ route('catalog') }}">Se alle hold →</a></div>
  </div>
@else
  <div class="course-grid">
    @foreach ($enrolledCourses as $course)
      <a href="{{ route('courses.show', $course) }}" class="card course-tile" aria-label="{{ $course->title }}">
        <div class="img-wrap">
          @include('partials.course-hero-thumb', ['course' => $course])
          <span class="course-tile-enrolled-badge"><i class="fa-solid fa-circle-check"></i> Tilmeldt</span>
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
