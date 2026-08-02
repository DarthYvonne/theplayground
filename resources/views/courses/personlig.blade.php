@extends('layouts.app')
@section('content')

@push('styles')
<style>
  a.course-tile { color: inherit; text-decoration: none; transition: transform 0.1s, box-shadow 0.1s; }
  a.course-tile:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(0,0,0,0.08); }
  .course-tile-title { font-size: 18px; line-height: 1.25; }
  .course-tile-sched { color: var(--muted); font-size: 13px; margin-top: 8px; display: flex; align-items: center; gap: 6px; }
  .course-tile-who { color: var(--muted); font-size: 13px; margin-top: 4px; }
  .course-tile-price { font-weight: 700; margin-top: 8px; }
  .course-tile-state { margin-top: 10px; }
  .pt-intro { color: var(--muted); font-size: 14px; line-height: 1.5; margin-bottom: 18px; max-width: 640px; }
  .empty-card { padding: 28px 20px; text-align: center; color: var(--muted); }
</style>
@endpush

<div class="view-header">
  <h1><i class="fa-solid fa-user-ninja" style="color:var(--text);margin-right:8px;"></i>Personlig træning</h1>
  @include('partials.header-actions', ['createType' => \App\Models\Course::TYPE_PERSONLIG])
</div>

<p class="pt-intro">Træning en-til-en med din træner. Du ser kun dine egne forløb her.</p>

@if ($courses->isEmpty())
  <div class="card">
    <div class="empty-card">Du har ingen personlig træning lige nu.</div>
  </div>
@else
  <div class="course-grid">
    @foreach ($courses as $course)
      @php
        $viewer = auth()->user();
        $manages = $viewer && ($viewer->isOwner() || $course->hasTrainer($viewer));
        $enrolled = $course->member_id && $course->member?->enrolledIn($course);
      @endphp
      <a href="{{ route('courses.show', $course) }}" class="card course-tile" aria-label="{{ $course->title }}">
        <div class="img-wrap">
          @include('partials.course-hero-thumb', ['course' => $course, 'placeholderIcon' => 'fa-user-ninja'])
        </div>
        <div class="card-pad">
          <div class="course-tile-title">{{ $course->title }}</div>
          @if ($course->scheduleLabel())
            <div class="course-tile-sched"><i class="fa-regular fa-clock"></i>{{ $course->scheduleLabel() }}</div>
          @endif
          <div class="course-tile-who"><i class="fa-regular fa-user" style="margin-right:4px;"></i>{{ $course->trainerNames() }}</div>
          <div class="course-tile-price">{{ $course->price() }}</div>

          <div class="course-tile-state">
            @if (! $course->member_id)
              {{-- Only a trainer or owner can see an unclaimed one at all. --}}
              <span class="tag muted">Afventer {{ $course->member_invite_email ?: $course->member_invite_phone }}</span>
            @elseif ($enrolled)
              <span class="tag success"><i class="fa-solid fa-circle-check"></i> Tilmeldt</span>
            @elseif ($manages)
              <span class="tag muted">Afventer betaling &mdash; {{ $course->member?->name }}</span>
            @else
              <span class="tag">Mangler betaling</span>
            @endif
          </div>
        </div>
      </a>
    @endforeach
  </div>
@endif

@endsection
