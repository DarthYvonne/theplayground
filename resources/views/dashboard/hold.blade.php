@extends('layouts.app')
@section('content')

@push('styles')
<style>
  .dash-main { max-width: 720px; }
  .course-mini { display: flex; gap: 14px; padding: 14px 18px; border-top: 1px solid #f0f2f5; }
  .course-mini:first-child { border-top: none; }
  .course-mini img, .course-mini .ph { width: 72px; height: 72px; border-radius: 10px; object-fit: cover; flex-shrink: 0; }
  .course-mini .ph { background: linear-gradient(135deg, var(--accent-soft), #f5f7fb); display: flex; align-items: center; justify-content: center; color: var(--accent); font-size: 26px; }
  .course-mini .meta { flex: 1; min-width: 0; }
  .course-mini .t { font-weight: 700; }
  .course-mini .sub { color: var(--muted); font-size: 13px; margin-top: 2px; }
  .course-mini .actions { display: flex; flex-direction: column; gap: 6px; }
  .empty { padding: 24px; text-align: center; color: var(--muted); }
  .empty a { color: var(--accent); font-weight: 600; }
  .section-h { padding: 14px 18px 8px; font-weight: 700; display: flex; align-items: center; gap: 8px; }
  @media (max-width: 767px) {
    .course-mini .actions { flex-direction: row; }
    .course-mini { flex-wrap: wrap; }
  }
</style>
@endpush

<div class="view-header">
  <h1>Start</h1>
  @include('partials.header-actions')
</div>

@include('dashboard._subnav')

<div class="dash-main">
  @if ($enrolledCourses->isNotEmpty())
    <div class="card">
      <div class="section-h"><i class="fa-solid fa-dumbbell" style="color:var(--accent)"></i> Dine hold</div>
      @foreach ($enrolledCourses as $c)
        <div class="course-mini">
          @if ($c->image_path)<img src="{{ $c->imageUrl() }}" alt="">@else<div class="ph"><i class="fa-solid fa-dumbbell"></i></div>@endif
          <div class="meta">
            <a href="{{ route('courses.show', $c) }}" class="t">{{ $c->title }}</a>
            <div class="sub">{{ $c->trainer->name }} · {{ $c->price() }}</div>
            @if ($c->scheduleLabel())<div class="sub"><i class="fa-regular fa-clock"></i> {{ $c->scheduleLabel() }}</div>@endif
          </div>
          <div class="actions">
            <a href="{{ route('courses.show', $c) }}" class="btn btn-secondary btn-sm">Detaljer</a>
          </div>
        </div>
      @endforeach
    </div>
  @elseif (!auth()->user()->isTrainer())
    <div class="card">
      <div class="section-h"><i class="fa-solid fa-dumbbell" style="color:var(--accent)"></i> Dine hold</div>
      <div class="empty">Du er ikke tilmeldt noget endnu. <a href="{{ url('/') }}">Se hold →</a></div>
    </div>
  @endif

  @if (auth()->user()->isTrainer() && $trainerCourses->count())
    <div class="card">
      <div class="section-h"><i class="fa-solid fa-chalkboard-user" style="color:var(--accent)"></i> Hold du underviser</div>
      @foreach ($trainerCourses as $c)
        <div class="course-mini">
          @if ($c->image_path)<img src="{{ $c->imageUrl() }}" alt="">@else<div class="ph"><i class="fa-solid fa-chalkboard-user"></i></div>@endif
          <div class="meta">
            <a href="{{ route('courses.show', $c) }}" class="t">{{ $c->title }}</a>
            <div class="sub">{{ $c->activeCount() }}/{{ $c->max_participants }} tilmeldte · {{ $c->is_active ? 'Aktiv' : 'Kladde' }}</div>
          </div>
          <div class="actions">
            <a href="{{ route('trainer.participants', $c) }}" class="btn btn-secondary btn-sm">Deltagere</a>
          </div>
        </div>
      @endforeach
    </div>
  @endif
</div>

@endsection
