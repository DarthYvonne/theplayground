@extends('layouts.app')
@section('content')

@push('styles')
<style>
  .members-shell { max-width: 720px; }

  .course-back { display: inline-flex; align-items: center; gap: 6px; color: var(--muted); font-size: 14px; font-weight: 600; margin-bottom: 14px; }
  .course-back:hover { color: var(--text); }

  .members-head { padding: 18px 22px; border-bottom: 1px solid #f0f2f5; }
  .members-head h2 { font-size: 18px; font-weight: 700; line-height: 1.2; }
  .members-head .sub { color: var(--muted); font-size: 13px; margin-top: 2px; }

  .members-sec { padding: 12px 6px 6px; font-size: 11px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: 0.4px; margin: 4px 16px 0; }
  .members-sec.has-action { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding-right: 4px; }
  .btn-broadcast { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 600; padding: 4px 10px; border-radius: 999px; background: var(--accent-soft); color: var(--accent); text-transform: none; letter-spacing: 0; }
  .btn-broadcast:hover { background: #dbe6fb; }

  .member-row { display: flex; gap: 12px; align-items: center; padding: 12px 18px; color: inherit; text-decoration: none; border-top: 1px solid #f0f2f5; transition: background 0.1s; }
  .member-row:first-of-type { border-top: none; }
  .member-row:hover { background: var(--hover); }
  .member-row .meta { flex: 1; min-width: 0; }
  .member-row .name { font-weight: 700; line-height: 1.2; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
  .member-row .sub { color: var(--muted); font-size: 13px; margin-top: 2px; }
  .member-row .chev { color: var(--muted); font-size: 14px; flex-shrink: 0; }

  .role-pill { font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 999px; background: var(--accent-soft); color: var(--accent); text-transform: uppercase; letter-spacing: 0.4px; }

  .empty { padding: 24px; text-align: center; color: var(--muted); }
</style>
@endpush

<div class="view-header">
  <h1>
    <a href="{{ route('courses.show', $course) }}" style="color:inherit;"><i class="fa-solid fa-arrow-left" style="font-size:16px;margin-right:8px;"></i></a>
    {{ $course->title }}
  </h1>
  @include('partials.header-actions')
</div>

@include('partials.course-tabs')

<div class="members-shell">
  <div class="card">
    @php $isCourseTrainer = auth()->check() && auth()->id() === $course->trainer_id; @endphp
    <div class="members-sec {{ $isCourseTrainer ? 'has-action' : '' }}">
      <span>Træner</span>
      @if ($isCourseTrainer)
        <a href="{{ route('beskeder.index', ['hold' => $course->id]) }}" class="btn-broadcast"><i class="fa-solid fa-bullhorn"></i> Skriv til alle</a>
      @endif
    </div>
    <a href="{{ route('members.show', $course->trainer) }}" class="member-row">
      @include('partials.avatar', ['u' => $course->trainer])
      <div class="meta">
        <div class="name">
          {{ $course->trainer->name }}
          <span class="role-pill">Træner</span>
        </div>
        @if ($course->trainer->about)
          <div class="sub">{{ \Illuminate\Support\Str::limit($course->trainer->about, 80) }}</div>
        @endif
      </div>
      <i class="fa-solid fa-chevron-right chev"></i>
    </a>

    <div class="members-sec">Deltagere ({{ $members->count() }})</div>
    @if ($members->isEmpty())
      <div class="empty">Ingen tilmeldte endnu.</div>
    @else
      @foreach ($members as $m)
        <a href="{{ route('members.show', $m) }}" class="member-row">
          @include('partials.avatar', ['u' => $m])
          <div class="meta">
            <div class="name">
              {{ $m->name }}
              @if ($m->id === auth()->id())<span class="role-pill" style="background:#dcfce7;color:#166534;">Dig</span>@endif
            </div>
            @if ($m->about)
              <div class="sub">{{ \Illuminate\Support\Str::limit($m->about, 80) }}</div>
            @endif
          </div>
          <i class="fa-solid fa-chevron-right chev"></i>
        </a>
      @endforeach
    @endif
  </div>
</div>

@endsection
