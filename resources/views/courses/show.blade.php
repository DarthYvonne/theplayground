@extends('layouts.app')
@section('content')

@push('styles')
<style>
  .feed-main { max-width: 720px; }
  .course-hero img { display: block; width: 100%; max-height: 360px; object-fit: cover; }
  .course-hero .ph { width: 100%; height: 220px; background: linear-gradient(135deg, var(--accent-soft), #f5f7fb); display: flex; align-items: center; justify-content: center; font-size: 80px; color: var(--accent); }
  .trainer-row { display: flex; gap: 12px; align-items: center; padding: 14px 18px; }
  .trainer-row .name { font-weight: 700; }
  .trainer-row .role { color: var(--muted); font-size: 12px; }
  .desc { padding: 14px 18px; line-height: 1.6; white-space: pre-wrap; color: #4b4f56; }
  .stats-row { display: flex; flex-wrap: wrap; gap: 18px; padding: 12px 18px; border-top: 1px solid #f0f2f5; border-bottom: 1px solid #f0f2f5; }
  .stat .label { font-size: 11px; text-transform: uppercase; letter-spacing: 0.4px; color: var(--muted); }
  .stat .val { font-size: 16px; font-weight: 700; margin-top: 2px; }
  .cta { padding: 16px 18px; display: flex; gap: 10px; flex-wrap: wrap; }
  @media (max-width: 767px) {
    .cta { flex-direction: column; }
    .cta .btn { width: 100%; justify-content: center; }
  }
</style>
@endpush

<div class="view-header">
  <h1><a href="{{ url('/') }}" style="color:inherit;"><i class="fa-solid fa-arrow-left" style="font-size:16px;margin-right:8px;"></i></a> {{ $course->title }}</h1>
  @include('partials.header-actions')
</div>

<div class="feed-main">
  <div class="card course-hero">
    @if ($course->image_path)
      <img src="{{ $course->imageUrl() }}" alt="">
    @else
      <div class="ph"><i class="fa-solid fa-dumbbell"></i></div>
    @endif
    <div class="trainer-row">
      @include('partials.avatar', ['u' => $course->trainer, 'size' => 'lg'])
      <div>
        <div class="name">{{ $course->trainer->name }}</div>
        <div class="role">Træner</div>
      </div>
      <div style="margin-left:auto;">
        @if ($course->isFull()) <span class="tag outline-danger"><i class="fa-solid fa-user-slash"></i> Fuldt booket</span>
        @else <span class="tag success">{{ $course->slotsLeft() }} {{ $course->slotsLeft() === 1 ? 'plads' : 'pladser' }} tilbage</span>
        @endif
      </div>
    </div>
    <div class="desc">{{ $course->description }}</div>
    <div class="stats-row">
      <div class="stat"><div class="label">Pris</div><div class="val">{{ $course->price() }}</div></div>
      <div class="stat"><div class="label">Tilmeldte</div><div class="val">{{ $course->activeCount() }}/{{ $course->max_participants }}</div></div>
      @if ($course->scheduleLabel())
        <div class="stat"><div class="label">Tidspunkt</div><div class="val">{{ $course->scheduleLabel() }}</div></div>
      @endif
      <div class="stat"><div class="label">Status</div><div class="val">{{ $course->is_active ? 'Aktiv' : 'Kladde' }}</div></div>
    </div>
    <div class="cta">
      @auth
        @if ($isEnrolled)
          <a href="{{ route('chat.course', $course) }}" class="btn btn-primary"><i class="fa-regular fa-comments"></i> Åbn holdets chat</a>
          <form method="POST" action="{{ route('enroll.cancel', $course) }}" onsubmit="return confirm('Afmeld dig dette hold?');">
            @csrf
            <button class="btn btn-danger" type="submit"><i class="fa-solid fa-xmark"></i> Afmeld</button>
          </form>
        @elseif ($course->isFull())
          <button class="btn btn-secondary" disabled><i class="fa-solid fa-lock"></i> Holdet er fuldt</button>
        @else
          <form method="POST" action="{{ route('enroll', $course) }}">
            @csrf
            <button class="btn btn-primary" type="submit"><i class="fa-solid fa-bolt"></i> Tilmeld dig — {{ $course->price() }}</button>
          </form>
        @endif
        @if (auth()->user()->isOwner() || $course->trainer_id === auth()->id())
          <a href="{{ route('trainer.broadcast', $course) }}" class="btn btn-secondary"><i class="fa-regular fa-envelope"></i> Skriv til deltagere</a>
          <a href="{{ route('trainer.participants', $course) }}" class="btn btn-secondary"><i class="fa-solid fa-users"></i> Deltagerliste</a>
        @endif
        @if (auth()->user()->isOwner())
          <a href="{{ route('admin.courses.edit', $course) }}" class="btn btn-ghost"><i class="fa-solid fa-pen"></i> Rediger</a>
        @endif
      @else
        <a href="{{ route('login') }}" class="btn btn-primary"><i class="fa-solid fa-bolt"></i> Log ind for at tilmelde dig</a>
      @endauth
    </div>
  </div>
</div>

@endsection
