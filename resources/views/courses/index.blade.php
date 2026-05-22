@extends('layouts.app')
@section('content')

@push('styles')
<style>
  .hero { background: linear-gradient(135deg, var(--accent) 0%, #6cabff 100%); color: #fff; padding: 24px 22px; border-radius: 14px; margin-bottom: 18px; }
  .hero h2 { font-size: 22px; font-weight: 800; margin-bottom: 6px; line-height: 1.2; }
  .hero p { font-size: 14px; opacity: 0.95; margin-bottom: 12px; }
  .hero .btn { background: #fff; color: var(--accent); }
  .hero .btn:hover { background: #f0f4fc; }
  .hero .btn.ghost { background: transparent; color: #fff; border: 1px solid rgba(255,255,255,0.6); margin-left: 6px; }
  .empty-feed { background: #fff; border-radius: 12px; padding: 60px 20px; text-align: center; color: var(--muted); }
  .course-tile-sched { color: var(--muted); font-size: 12px; margin-top: 4px; }
  .course-tile-price { font-weight: 700; color: var(--text); font-size: 14px; margin-top: 6px; }
</style>
@endpush

<div class="view-header">
  <h1>Hold</h1>
  @include('partials.header-actions')
</div>

@include('courses._subnav')

@guest
<div class="hero">
  <h2>Find dit næste hold</h2>
  <p>Se hvilke hold der kører på The Playground. Opret en konto for at tilmelde dig og chatte med trænere og andre medlemmer.</p>
  <a href="{{ route('register') }}" class="btn">Kom i gang</a>
  <a href="{{ route('login') }}" class="btn ghost">Log ind</a>
</div>
@endguest

@if ($courses->isEmpty())
  <div class="empty-feed">
    <h3 style="color:var(--text);margin-bottom:6px;">Ingen hold endnu</h3>
    <p>Kom tilbage om lidt.</p>
  </div>
@else
  <div class="course-grid">
    @foreach ($courses as $course)
      @php $full = $course->active_enrollments_count >= $course->max_participants; @endphp
      <div class="card course-tile">
        <a href="{{ route('courses.show', $course) }}">
          @if ($course->image_path)
            <img src="{{ $course->imageUrl() }}" alt="" class="course-tile-img">
          @else
            <div class="course-tile-img course-tile-img-ph"><i class="fa-solid fa-dumbbell"></i></div>
          @endif
        </a>
        <div class="card-pad">
          <a href="{{ route('courses.show', $course) }}" style="color:inherit;">
            <div class="course-tile-title">{{ $course->title }}</div>
          </a>
          <div class="course-tile-meta">
            {{ $course->active_enrollments_count }}/{{ $course->max_participants }} tilmeldt ·
            @if ($full)<span style="color:#b91c1c;font-weight:600;">Fuldt booket</span>
            @else<span style="color:#166534;font-weight:600;">{{ $course->slotsLeft() }} {{ $course->slotsLeft() === 1 ? 'plads' : 'pladser' }} tilbage</span>@endif
          </div>
          @if ($course->scheduleLabel())
            <div class="course-tile-sched"><i class="fa-regular fa-clock" style="margin-right:4px;"></i>{{ $course->scheduleLabel() }}</div>
          @endif
          <div class="course-tile-price">{{ $course->price() }}</div>
          <div class="course-tile-actions">
            <a href="{{ route('courses.show', $course) }}" class="btn btn-secondary btn-sm"><i class="fa-regular fa-eye"></i> Læs mere</a>
            @auth
              @if (auth()->user()->enrolledIn($course))
                <a href="{{ route('chat.course', $course) }}" class="btn btn-primary btn-sm"><i class="fa-regular fa-comments"></i> Chat</a>
              @elseif ($full)
                <button class="btn btn-secondary btn-sm" disabled><i class="fa-solid fa-lock"></i> Fuldt</button>
              @else
                <form method="POST" action="{{ route('enroll', $course) }}" style="display:inline-flex;">
                  @csrf
                  <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-bolt"></i> Tilmeld</button>
                </form>
              @endif
            @else
              <a href="{{ route('login') }}" class="btn btn-primary btn-sm"><i class="fa-solid fa-bolt"></i> Tilmeld</a>
            @endauth
          </div>
        </div>
      </div>
    @endforeach
  </div>
@endif

@endsection
