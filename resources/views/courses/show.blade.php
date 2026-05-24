@extends('layouts.app')
@section('content')

@push('styles')
<style>
  .course-detail { max-width: 720px; }

  .hero-img { display: block; width: 100%; max-height: 360px; object-fit: cover; }
  .hero-ph { width: 100%; height: 240px; background: linear-gradient(135deg, var(--accent-soft), #f5f7fb); display: flex; align-items: center; justify-content: center; font-size: 80px; color: var(--accent); }

  .course-body { padding: 24px; }
  .course-title { font-size: 28px; font-weight: 800; line-height: 1.2; }

  .info-ribbon { display: flex; flex-wrap: wrap; gap: 6px 18px; margin-top: 14px; padding: 12px 0; border-top: 1px solid #f0f2f5; border-bottom: 1px solid #f0f2f5; color: var(--muted); font-size: 14px; }
  .info-ribbon .item { display: inline-flex; align-items: center; gap: 6px; }
  .info-ribbon .item i { font-size: 12px; opacity: 0.85; }
  .info-ribbon .item strong { color: var(--text); font-weight: 600; }
  .info-ribbon .full { color: #b91c1c; font-weight: 700; }
  .info-ribbon .draft { color: #b91c1c; font-weight: 700; }

  .trainer-line { display: flex; align-items: center; gap: 8px; margin-top: 14px; color: var(--muted); font-size: 13px; }
  .trainer-line .name { color: var(--text); font-weight: 600; }

  .desc { margin-top: 18px; line-height: 1.6; white-space: pre-wrap; color: #3a3d42; }

  .card-footer { background: #fafbfc; border-top: 1px solid #f0f2f5; padding: 16px 24px; display: flex; gap: 14px; align-items: center; flex-wrap: wrap; }
  .card-footer .footer-left { display: flex; gap: 14px; align-items: center; flex-wrap: wrap; flex: 1; min-width: 0; }
  .card-footer .afmeld { background: none; border: none; padding: 0; color: var(--muted); font-size: 13px; cursor: pointer; font-family: inherit; text-decoration: underline; text-underline-offset: 3px; }
  .card-footer .afmeld:hover { color: var(--danger); }
  .card-footer .enrolled-note { display: inline-flex; align-items: center; gap: 6px; color: #166534; font-weight: 700; font-size: 14px; }

  .card-footer .owner-actions { display: inline-flex; gap: 4px; margin-left: auto; }
  .cf-iconbtn { width: 36px; height: 36px; display: inline-flex; align-items: center; justify-content: center; border: 1px solid var(--border); background: #fff; color: var(--muted); border-radius: 8px; cursor: pointer; font-size: 14px; padding: 0; }
  .cf-iconbtn:hover { background: var(--hover); color: var(--text); border-color: var(--muted); }
  .cf-iconbtn.danger:hover { background: #fef2f2; color: var(--danger); border-color: var(--danger); }
  .card-footer .owner-actions form { display: inline-flex; margin: 0; }

  @media (max-width: 767px) {
    .course-body { padding: 18px; }
    .course-title { font-size: 22px; }
    .card-footer { padding: 14px 18px; }
    .card-footer .footer-left .btn { width: 100%; justify-content: center; }
  }
</style>
@endpush

<div class="view-header">
  <h1>
    <a href="{{ url('/') }}" style="color:inherit;"><i class="fa-solid fa-arrow-left" style="font-size:16px;margin-right:8px;"></i></a>
    {{ $course->title }}
  </h1>
  @include('partials.header-actions')
</div>

@include('partials.course-tabs')

<div class="course-detail">
  <div class="card">
    @if ($course->image_path)
      <img src="{{ $course->imageUrl() }}" alt="" class="hero-img">
    @else
      <div class="hero-ph"><i class="fa-solid fa-dumbbell"></i></div>
    @endif

    <div class="course-body">
      <h1 class="course-title">{{ $course->title }}</h1>

      <div class="info-ribbon">
        <span class="item"><strong>{{ $course->price() }}</strong></span>
        @if ($course->free_enrollment)
          <span class="item" style="color:#166534;font-weight:700;"><i class="fa-solid fa-gift"></i> Gratis tilmelding</span>
        @endif
        @if ($course->scheduleLabel())
          <span class="item"><i class="fa-regular fa-clock"></i>{{ $course->scheduleLabel() }}</span>
        @endif
        <span class="item">
          <i class="fa-regular fa-user"></i>
          <strong>{{ $course->activeCount() }}/{{ $course->max_participants }}</strong> tilmeldt
          @if ($course->isFull())<span class="full">· Fuldt</span>@endif
        </span>
        @if (auth()->user()?->isOwner() && !$course->is_active)
          <span class="item draft">Kladde</span>
        @endif
      </div>

      <div class="trainer-line">
        @include('partials.avatar', ['u' => $course->trainer, 'size' => 'sm'])
        <span>Med <span class="name">{{ $course->trainer->name }}</span></span>
      </div>

      @if (trim((string) $course->description) !== '')
        <div class="desc">{{ $course->description }}</div>
      @endif
    </div>

    <div class="card-footer">
      <div class="footer-left">
        @auth
          @if ($isEnrolled)
            <form method="POST" action="{{ route('enroll.cancel', $course) }}" onsubmit="return confirm('Afmeld dig dette hold?');">
              @csrf
              <button class="afmeld" type="submit">Afmeld</button>
            </form>
            <span class="enrolled-note"><i class="fa-solid fa-circle-check"></i> Du er tilmeldt</span>
          @elseif ($course->isFull())
            <button class="btn btn-secondary" disabled>Holdet er fuldt</button>
          @else
            <form method="POST" action="{{ route('enroll', $course) }}">
              @csrf
              <button class="btn btn-primary" type="submit">Tilmeld dig</button>
            </form>
          @endif
        @else
          <a href="{{ route('login') }}" class="btn btn-primary">Log ind for at tilmelde dig</a>
        @endauth
      </div>

      @auth
        @if (auth()->user()->isOwner())
          <div class="owner-actions">
            <a href="{{ route('admin.courses.edit', $course) }}" class="cf-iconbtn" title="Rediger hold" aria-label="Rediger hold"><i class="fa-solid fa-pen"></i></a>
            <form method="POST" action="{{ route('admin.courses.destroy', $course) }}" onsubmit="return confirm('Slet dette hold? Det kan ikke fortrydes.');">
              @csrf
              <button class="cf-iconbtn danger" type="submit" title="Slet hold" aria-label="Slet hold"><i class="fa-solid fa-trash"></i></button>
            </form>
          </div>
        @endif
      @endauth
    </div>
  </div>
</div>

@endsection
