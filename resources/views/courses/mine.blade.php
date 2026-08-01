@extends('layouts.app')
@section('content')

@push('styles')
<style>
  a.course-tile { color: inherit; text-decoration: none; transition: transform 0.1s, box-shadow 0.1s; }
  a.course-tile:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(0,0,0,0.08); }
  .course-tile .img-wrap { position: relative; }
  .course-tile-title { font-size: 18px; line-height: 1.25; }
  .course-tile-sched { color: var(--muted); font-size: 13px; margin-top: 8px; display: flex; align-items: center; gap: 6px; }
  .course-tile-sched.is-today { color: var(--accent); font-weight: 700; }
  .course-tile-sched.is-cancelled { color: #b91c1c; font-weight: 700; }
  .course-tile-trainer { color: var(--muted); font-size: 13px; margin-top: 4px; }
  .tile-badge { position: absolute; top: 10px; right: 10px; color: #fff; font-size: 12px; font-weight: 700; padding: 4px 10px; border-radius: 999px; box-shadow: 0 2px 6px rgba(0,0,0,0.18); display: inline-flex; align-items: center; gap: 5px; }
  .tile-badge.today { background: var(--accent); }
  .tile-badge.cancelled { background: #dc2626; }
  .tile-unread { position: absolute; top: 10px; left: 10px; background: #dc2626; color: #fff; font-size: 12px; font-weight: 700; padding: 4px 10px; border-radius: 999px; box-shadow: 0 2px 6px rgba(0,0,0,0.18); display: inline-flex; align-items: center; gap: 5px; }
  .tile-pay-chip { margin-top: 8px; display: inline-flex; align-items: center; gap: 6px; background: #fef3c7; color: #92400e; font-size: 12px; font-weight: 700; padding: 4px 10px; border-radius: 999px; }
  .pay-alert { display: flex; gap: 12px; align-items: flex-start; background: #fffbeb; border: 1px solid #fde68a; border-radius: 12px; padding: 14px 16px; margin-bottom: 18px; }
  .pay-alert i { color: #d97706; font-size: 18px; margin-top: 2px; }
  .pay-alert .txt { font-size: 14px; line-height: 1.5; color: #92400e; }
  .pay-alert a { color: var(--accent); font-weight: 700; }
  .empty-card { padding: 28px 20px; text-align: center; color: var(--muted); }
  .empty-card a { color: var(--accent); font-weight: 600; }
  .empty-card .lead { color: var(--text); font-weight: 600; margin-bottom: 6px; }
</style>
@endpush

<div class="view-header">
  <h1><i class="fa-solid fa-dumbbell" style="color:var(--text);margin-right:8px;"></i>Hold</h1>
  @include('partials.header-actions')
</div>

@include('courses._subnav')

@if ($needsPayment->isNotEmpty())
  <div class="pay-alert">
    <i class="fa-solid fa-triangle-exclamation"></i>
    <div class="txt">
      Der mangler betaling på
      <strong>{{ $needsPayment->map(fn ($t) => $t['course']->title)->join(', ', ' og ') }}</strong>.
      Du har stadig adgang, men ordn det gerne under <a href="{{ route('profile.billing') }}">Betaling</a>.
    </div>
  </div>
@endif

@if ($tiles->isEmpty())
  <div class="card">
    <div class="empty-card">
      <div class="lead">Du er ikke tilmeldt noget endnu</div>
      <p style="margin-bottom:12px;">Når du er tilmeldt et hold, kan du her se hvornår I træner næste gang, og om der er nye beskeder.</p>
      <a href="{{ route('catalog') }}">Se alle hold →</a>
    </div>
  </div>
@else
  <div class="course-grid">
    @foreach ($tiles as $tile)
      @php $course = $tile['course']; @endphp
      <a href="{{ route('courses.show', $course) }}" class="card course-tile" aria-label="{{ $course->title }}">
        <div class="img-wrap">
          @include('partials.course-hero-thumb', ['course' => $course])
          @if ($tile['cancelled_today'])
            <span class="tile-badge cancelled"><i class="fa-solid fa-ban"></i> Aflyst i dag</span>
          @elseif ($tile['is_today'])
            <span class="tile-badge today"><i class="fa-regular fa-clock"></i> I dag</span>
          @endif
          @if ($tile['unread'] > 0)
            <span class="tile-unread" title="{{ $tile['unread'] }} nye beskeder">
              <i class="fa-solid fa-comment"></i> {{ $tile['unread'] }} {{ $tile['unread'] === 1 ? 'ny' : 'nye' }}
            </span>
          @endif
        </div>
        <div class="card-pad">
          <div class="course-tile-title">{{ $course->title }}</div>

          @if ($tile['cancelled_today'])
            <div class="course-tile-sched is-cancelled"><i class="fa-solid fa-ban"></i>Aflyst i dag</div>
            @if ($tile['next_label'])
              <div class="course-tile-sched"><i class="fa-regular fa-clock"></i>Næste: {{ $tile['next_label'] }}</div>
            @endif
          @elseif ($tile['next_label'])
            <div class="course-tile-sched {{ $tile['is_today'] ? 'is-today' : '' }}">
              <i class="fa-regular fa-clock"></i>{{ $tile['next_label'] }}
            </div>
          @elseif ($course->scheduleLabel())
            <div class="course-tile-sched"><i class="fa-regular fa-clock"></i>{{ $course->scheduleLabel() }}</div>
          @endif

          <div class="course-tile-trainer"><i class="fa-regular fa-user" style="margin-right:4px;"></i>{{ $course->trainerNames() }}</div>

          @if ($tile['status'] !== 'active')
            <div class="tile-pay-chip"><i class="fa-solid fa-triangle-exclamation"></i> Betaling mangler</div>
          @endif
        </div>
      </a>
    @endforeach
  </div>
@endif

@endsection
