@extends('layouts.app')
@section('content')

@push('styles')
<style>
  .ph-section { margin-bottom: 18px; }
  .ph-section-head { font-size: 13px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: 0.4px; margin-bottom: 10px; }
  .ph-empty { background: #fff; border-radius: 12px; padding: 28px 20px; text-align: left; color: var(--muted); box-shadow: 0 1px 2px rgba(0,0,0,0.08); }
  .ph-empty a { color: var(--accent); font-weight: 600; }
</style>
@endpush

<div class="view-header">
  <h1>Min profil</h1>
  @include('partials.header-actions')
</div>

@include('profile._subnav')

@if (auth()->user()->isTrainer() && $trainerCourses->count())
  <div class="ph-section">
    <div class="ph-section-head">Hold du underviser</div>
    <div class="course-grid">
      @foreach ($trainerCourses as $c)
        <div class="card course-tile">
          @if ($c->image_path)
            <img src="{{ $c->imageUrl() }}" alt="" class="course-tile-img">
          @else
            <div class="course-tile-img course-tile-img-ph"><i class="fa-solid fa-chalkboard-user"></i></div>
          @endif
          <div class="card-pad">
            <div class="course-tile-title">{{ $c->title }}</div>
            <div class="course-tile-meta">{{ $c->activeCount() }}/{{ $c->max_participants }} tilmeldte · {{ $c->is_active ? 'Aktiv' : 'Kladde' }}</div>
            <div class="course-tile-actions">
              <a href="{{ route('chat.course', $c) }}" class="btn btn-primary btn-sm"><i class="fa-regular fa-comments"></i> Chat</a>
              <a href="{{ route('trainer.participants', $c) }}" class="btn btn-secondary btn-sm"><i class="fa-solid fa-users"></i> Deltagere</a>
            </div>
          </div>
        </div>
      @endforeach
    </div>
  </div>
@endif

<div class="ph-section">
  <div class="ph-section-head">Tilmeldt på</div>
  @if ($enrolledCourses->isEmpty())
    <div class="ph-empty">Du er ikke tilmeldt noget endnu. <a href="{{ url('/') }}">Se hold →</a></div>
  @else
    <div class="course-grid">
      @foreach ($enrolledCourses as $c)
        <div class="card course-tile">
          @if ($c->image_path)
            <img src="{{ $c->imageUrl() }}" alt="" class="course-tile-img">
          @else
            <div class="course-tile-img course-tile-img-ph"><i class="fa-solid fa-dumbbell"></i></div>
          @endif
          <div class="card-pad">
            <div class="course-tile-title">{{ $c->title }}</div>
            <div class="course-tile-meta">{{ count($c->trainers) === 1 ? 'Træner' : 'Trænere' }} {{ $c->trainerNames() }} · {{ $c->price() }}</div>
            @if ($c->scheduleLabel())
              <div class="course-tile-meta" style="margin-top:4px;"><i class="fa-regular fa-clock" style="margin-right:4px;"></i>{{ $c->scheduleLabel() }}</div>
            @endif
            <div class="course-tile-actions">
              <a href="{{ route('chat.course', $c) }}" class="btn btn-primary btn-sm"><i class="fa-regular fa-comments"></i> Chat</a>
              <a href="{{ route('courses.show', $c) }}" class="btn btn-secondary btn-sm"><i class="fa-regular fa-eye"></i> Detaljer</a>
            </div>
          </div>
        </div>
      @endforeach
    </div>
  @endif
</div>

@endsection
