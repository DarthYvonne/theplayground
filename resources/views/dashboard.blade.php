@extends('layouts.app')
@section('content')

@push('styles')
<style>
  .feed-main { max-width: 720px; margin: 0 auto; }
  .greet { padding: 18px 20px; }
  .greet h2 { font-size: 22px; font-weight: 800; }
  .greet p { color: var(--muted); margin-top: 4px; }
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
  <h1>Dashboard</h1>
  @include('partials.header-actions')
</div>

<div class="feed-main">
  <div class="card greet">
    <h2>Hi {{ explode(' ', auth()->user()->name)[0] }} 👋</h2>
    <p>Welcome back to The Playground. Here's what's happening.</p>
  </div>

  <div class="card">
    <div class="section-h"><i class="fa-solid fa-dumbbell" style="color:var(--accent)"></i> My courses</div>
    @if ($enrolledCourses->isEmpty())
      <div class="empty">You're not enrolled in anything yet. <a href="{{ url('/') }}">Browse courses →</a></div>
    @else
      @foreach ($enrolledCourses as $c)
        <div class="course-mini">
          @if ($c->image_path)<img src="{{ $c->imageUrl() }}" alt="">@else<div class="ph"><i class="fa-solid fa-dumbbell"></i></div>@endif
          <div class="meta">
            <a href="{{ route('courses.show', $c) }}" class="t">{{ $c->title }}</a>
            <div class="sub">{{ $c->trainer->name }} · {{ $c->price() }}</div>
          </div>
          <div class="actions">
            <a href="{{ route('chat.course', $c) }}" class="btn btn-primary btn-sm"><i class="fa-regular fa-comments"></i> Chat</a>
            <a href="{{ route('courses.show', $c) }}" class="btn btn-secondary btn-sm">Details</a>
          </div>
        </div>
      @endforeach
    @endif
  </div>

  @if ($trainerCourses->count())
    <div class="card">
      <div class="section-h"><i class="fa-solid fa-chalkboard-user" style="color:var(--accent)"></i> Courses I teach</div>
      @foreach ($trainerCourses as $c)
        <div class="course-mini">
          @if ($c->image_path)<img src="{{ $c->imageUrl() }}" alt="">@else<div class="ph"><i class="fa-solid fa-chalkboard-user"></i></div>@endif
          <div class="meta">
            <a href="{{ route('courses.show', $c) }}" class="t">{{ $c->title }}</a>
            <div class="sub">{{ $c->activeCount() }}/{{ $c->max_participants }} enrolled · {{ $c->is_active ? 'Active' : 'Draft' }}</div>
          </div>
          <div class="actions">
            <a href="{{ route('trainer.broadcast', $c) }}" class="btn btn-secondary btn-sm"><i class="fa-regular fa-envelope"></i> Email</a>
            <a href="{{ route('chat.course', $c) }}" class="btn btn-primary btn-sm"><i class="fa-regular fa-comments"></i> Chat</a>
          </div>
        </div>
      @endforeach
    </div>
  @endif
</div>

@endsection
