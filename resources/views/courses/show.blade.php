@extends('layouts.app')
@section('content')

@push('styles')
<style>
  .course-detail { max-width: 720px; }

  .course-back { display: inline-flex; align-items: center; gap: 6px; color: var(--muted); font-size: 14px; font-weight: 600; margin-bottom: 14px; }
  .course-back:hover { color: var(--text); }

  .hero-img { display: block; width: 100%; max-height: 360px; object-fit: cover; }
  .hero-ph { width: 100%; height: 240px; background: linear-gradient(135deg, var(--accent-soft), #f5f7fb); display: flex; align-items: center; justify-content: center; font-size: 80px; color: var(--accent); }

  .course-body { padding: 22px 24px; }
  .course-title { font-size: 28px; font-weight: 800; line-height: 1.2; }
  .course-trainer { display: flex; align-items: center; gap: 10px; margin-top: 14px; color: var(--muted); font-size: 14px; }
  .course-trainer .name { color: var(--text); font-weight: 600; }

  .meta-strip { display: flex; flex-wrap: wrap; gap: 18px 22px; margin-top: 18px; padding-top: 16px; border-top: 1px solid #f0f2f5; }
  .meta-strip .item { display: flex; align-items: center; gap: 8px; font-size: 14px; }
  .meta-strip .item i { color: var(--muted); width: 16px; text-align: center; }
  .meta-strip .item .val { font-weight: 600; }
  .meta-strip .item .lbl { color: var(--muted); }

  .desc { margin-top: 18px; padding-top: 16px; border-top: 1px solid #f0f2f5; line-height: 1.6; white-space: pre-wrap; color: #3a3d42; }

  .cta { margin-top: 20px; display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }

  .card-footer { background: #fafbfc; border-top: 1px solid #f0f2f5; padding: 16px 24px; }
  .card-footer-label { font-size: 11px; text-transform: uppercase; letter-spacing: 0.4px; color: var(--muted); font-weight: 700; margin-bottom: 10px; }
  .card-footer-actions { display: flex; gap: 8px; flex-wrap: wrap; }

  @media (max-width: 767px) {
    .course-body { padding: 18px; }
    .course-title { font-size: 22px; }
    .cta .btn { width: 100%; justify-content: center; }
    .card-footer { padding: 14px 18px; }
    .card-footer-actions .btn { flex: 1; justify-content: center; }
  }
</style>
@endpush

<div class="view-header">
  @include('partials.header-actions')
</div>

<div class="course-detail">
  <a href="{{ url('/') }}" class="course-back"><i class="fa-solid fa-arrow-left"></i> Tilbage til hold</a>

  <div class="card">
    @if ($course->image_path)
      <img src="{{ $course->imageUrl() }}" alt="" class="hero-img">
    @else
      <div class="hero-ph"><i class="fa-solid fa-dumbbell"></i></div>
    @endif

    <div class="course-body">
      <h1 class="course-title">{{ $course->title }}</h1>

      <div class="course-trainer">
        @include('partials.avatar', ['u' => $course->trainer, 'size' => 'sm'])
        <span>Med <span class="name">{{ $course->trainer->name }}</span></span>
        <span style="margin-left:auto;">
          @if ($course->isFull())
            <span class="tag outline-danger">Fuldt booket</span>
          @else
            <span class="tag success">{{ $course->slotsLeft() }} {{ $course->slotsLeft() === 1 ? 'plads' : 'pladser' }} tilbage</span>
          @endif
        </span>
      </div>

      <div class="meta-strip">
        <div class="item"><i class="fa-solid fa-tag"></i><span class="val">{{ $course->price() }}</span></div>
        <div class="item"><i class="fa-regular fa-user"></i><span class="val">{{ $course->activeCount() }}/{{ $course->max_participants }}</span><span class="lbl">tilmeldt</span></div>
        @if ($course->scheduleLabel())
          <div class="item"><i class="fa-regular fa-clock"></i><span class="val">{{ $course->scheduleLabel() }}</span></div>
        @endif
        @if (auth()->user()?->isOwner() && !$course->is_active)
          <div class="item"><i class="fa-solid fa-eye-slash"></i><span class="val" style="color:#b91c1c;">Kladde</span></div>
        @endif
      </div>

      @if (trim((string) $course->description) !== '')
        <div class="desc">{{ $course->description }}</div>
      @endif

      <div class="cta">
        @auth
          @if ($isEnrolled)
            <a href="{{ route('chat.course', $course) }}" class="btn btn-primary"><i class="fa-regular fa-comments"></i> Åbn holdets chat</a>
            <form method="POST" action="{{ route('enroll.cancel', $course) }}" onsubmit="return confirm('Afmeld dig dette hold?');">
              @csrf
              <button class="btn btn-ghost" type="submit" style="color:var(--danger);">Afmeld</button>
            </form>
          @elseif ($course->isFull())
            <button class="btn btn-secondary" disabled><i class="fa-solid fa-lock"></i> Holdet er fuldt</button>
          @else
            <form method="POST" action="{{ route('enroll', $course) }}">
              @csrf
              <button class="btn btn-primary" type="submit">Tilmeld dig — {{ $course->price() }}</button>
            </form>
          @endif
        @else
          <a href="{{ route('login') }}" class="btn btn-primary">Log ind for at tilmelde dig</a>
        @endauth
      </div>
    </div>

    @auth
      @php $canManage = auth()->user()->isOwner() || $course->trainer_id === auth()->id(); @endphp
      @if ($canManage)
        <div class="card-footer">
          <div class="card-footer-label">Holdadministration</div>
          <div class="card-footer-actions">
            @if (auth()->user()->isOwner())
              <a href="{{ route('admin.courses.edit', $course) }}" class="btn btn-secondary btn-sm"><i class="fa-solid fa-pen"></i> Rediger hold</a>
            @endif
            <a href="{{ route('trainer.participants', $course) }}" class="btn btn-secondary btn-sm"><i class="fa-solid fa-users"></i> Deltagere</a>
            <a href="{{ route('trainer.broadcast', $course) }}" class="btn btn-secondary btn-sm"><i class="fa-regular fa-envelope"></i> Skriv til deltagere</a>
          </div>
        </div>
      @endif
    @endauth
  </div>
</div>

@endsection
