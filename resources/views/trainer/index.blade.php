@extends('layouts.app')
@section('content')

@push('styles')
<style>
  .course-mini { display: flex; gap: 14px; padding: 14px 18px; border-top: 1px solid #f0f2f5; }
  .course-mini:first-child { border-top: none; }
  .course-mini img, .course-mini .ph { width: 72px; height: 72px; border-radius: 10px; object-fit: cover; flex-shrink: 0; }
  .course-mini .ph { background: linear-gradient(135deg, var(--accent-soft), #f5f7fb); display: flex; align-items: center; justify-content: center; color: var(--accent); font-size: 26px; }
  .course-mini .meta { flex: 1; min-width: 0; }
  .course-mini .t { font-weight: 700; }
  .course-mini .sub { color: var(--muted); font-size: 13px; margin-top: 2px; }
  .course-mini .actions { display: flex; flex-direction: column; gap: 6px; }
  .empty { padding: 24px; text-align: center; color: var(--muted); }
  @media (max-width: 767px) {
    .course-mini .actions { flex-direction: row; flex-wrap: wrap; }
    .course-mini { flex-wrap: wrap; }
  }
</style>
@endpush

<div class="view-header">
  <h1>Hold du træner</h1>
  @include('partials.header-actions')
</div>

@include('trainer._subnav')

@if ($courses->isEmpty())
  <div class="card">
    <div class="empty">Du er ikke tilknyttet nogen hold endnu.</div>
  </div>
@else
  <div class="card">
    @foreach ($courses as $c)
      <div class="course-mini">
        @if ($c->image_path)<img src="{{ $c->imageUrl() }}" alt="">@else<div class="ph"><i class="fa-solid fa-chalkboard-user"></i></div>@endif
        <div class="meta">
          <a href="{{ route('courses.show', $c) }}" class="t">{{ $c->title }}</a>
          <div class="sub">{{ $c->activeCount() }}/{{ $c->max_participants }} tilmeldte · {{ $c->is_active ? 'Aktiv' : 'Kladde' }}</div>
          @if ($c->scheduleLabel())<div class="sub"><i class="fa-regular fa-clock"></i> {{ $c->scheduleLabel() }}</div>@endif
        </div>
        <div class="actions">
          <a href="{{ route('chat.course', $c) }}" class="btn btn-primary btn-sm"><i class="fa-regular fa-comments"></i> Chat</a>
          <a href="{{ route('trainer.broadcast', $c) }}" class="btn btn-secondary btn-sm"><i class="fa-regular fa-envelope"></i> Skriv</a>
          <a href="{{ route('trainer.participants', $c) }}" class="btn btn-secondary btn-sm"><i class="fa-solid fa-users"></i> Deltagere</a>
        </div>
      </div>
    @endforeach
  </div>
@endif

@endsection
