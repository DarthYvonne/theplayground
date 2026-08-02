@extends('layouts.app')
@section('content')

@push('styles')
<style>
  a.course-tile { color: inherit; text-decoration: none; transition: transform 0.1s, box-shadow 0.1s; }
  a.course-tile:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(0,0,0,0.08); }
  .course-tile-title { font-size: 18px; line-height: 1.25; }
  .course-tile-sched { color: var(--muted); font-size: 13px; margin-top: 8px; display: flex; align-items: center; gap: 6px; }
  .course-tile-trainer { color: var(--muted); font-size: 13px; margin-top: 4px; }
  .course-tile-price { font-weight: 700; margin-top: 8px; }
  .course-tile-status { color: var(--muted); font-size: 13px; margin-top: 2px; }
  .course-tile-enrolled-badge { position: absolute; top: 8px; right: 8px; background: rgba(22,101,52,0.92); color: #fff; font-size: 11px; font-weight: 700; padding: 3px 9px; border-radius: 12px; display: inline-flex; align-items: center; gap: 4px; }
  .pt-intro { color: var(--muted); font-size: 14px; line-height: 1.5; margin-bottom: 18px; max-width: 640px; }
  .empty-card { padding: 28px 20px; text-align: center; color: var(--muted); }
</style>
@endpush

<div class="view-header">
  <h1><i class="fa-solid fa-user-ninja" style="color:var(--text);margin-right:8px;"></i>Personlig træning</h1>
  @include('partials.header-actions', ['createType' => \App\Models\Course::TYPE_PERSONLIG])
</div>

<p class="pt-intro">Træning på egne præmisser, tæt på en træner. Klik på et forløb for at se tider og pris.</p>

@if ($courses->isEmpty())
  <div class="card">
    <div class="empty-card">Der er ingen personlig træning lige nu.</div>
  </div>
@else
  <div class="course-grid">
    @foreach ($courses as $course)
      @php
        $full = $course->active_enrollments_count >= $course->max_participants;
        $enrolled = auth()->check() && auth()->user()->enrolledIn($course);
      @endphp
      <a href="{{ route('courses.show', $course) }}" class="card course-tile" aria-label="{{ $course->title }}">
        <div class="img-wrap">
          @include('partials.course-hero-thumb', ['course' => $course])
          @if ($enrolled)
            <span class="course-tile-enrolled-badge"><i class="fa-solid fa-circle-check"></i> Tilmeldt</span>
          @endif
        </div>
        <div class="card-pad">
          <div class="course-tile-title">{{ $course->title }}</div>
          @if ($course->scheduleLabel())
            <div class="course-tile-sched"><i class="fa-regular fa-clock"></i>{{ $course->scheduleLabel() }}</div>
          @endif
          <div class="course-tile-trainer"><i class="fa-regular fa-user" style="margin-right:4px;"></i>{{ $course->trainerNames() }}</div>
          <div class="course-tile-price">{{ $course->price() }}</div>
          <div class="course-tile-status">
            {{ $course->active_enrollments_count }}/{{ $course->max_participants }} tilmeldt
            @if ($full) · <span style="color:#b91c1c;font-weight:600;">Fuldt</span>@endif
          </div>
        </div>
      </a>
    @endforeach
  </div>
@endif

@endsection
