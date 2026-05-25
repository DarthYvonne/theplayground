@extends('layouts.app')
@section('content')

@push('styles')
<style>
  .members-back { display: inline-flex; align-items: center; gap: 6px; color: var(--muted); font-size: 14px; font-weight: 600; margin-bottom: 14px; }
  .members-back:hover { color: var(--text); }

  .member-hero { padding: 24px; display: flex; gap: 18px; align-items: center; }
  .member-hero .info { min-width: 0; flex: 1; }
  .member-hero .name { font-size: 24px; font-weight: 800; line-height: 1.2; }
  .member-hero .role { color: var(--muted); margin-top: 4px; font-size: 14px; }
  .member-hero .actions { display: flex; gap: 8px; flex-wrap: wrap; }

  .member-section { padding: 18px 24px; border-top: 1px solid #f0f2f5; }
  .member-section h2 { font-size: 13px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: 0.4px; margin-bottom: 10px; }
  .member-section .about { line-height: 1.6; white-space: pre-wrap; color: #3a3d42; }

  .course-list { display: flex; flex-direction: column; gap: 8px; }
  .course-list a { display: flex; gap: 12px; align-items: center; padding: 10px 12px; border: 1px solid #f0f2f5; border-radius: 10px; color: inherit; }
  .course-list a:hover { background: var(--hover); }
  .course-list img, .course-list .ph { width: 44px; height: 44px; border-radius: 8px; object-fit: cover; flex-shrink: 0; background: var(--accent-soft); color: var(--accent); display: flex; align-items: center; justify-content: center; font-size: 16px; }
  .course-list .t { font-weight: 700; }
  .course-list .sub { color: var(--muted); font-size: 12px; margin-top: 1px; }

  .empty-line { color: var(--muted); font-size: 14px; font-style: italic; }

  @media (max-width: 540px) {
    .member-hero { flex-direction: column; align-items: flex-start; text-align: left; padding: 20px; }
  }
</style>
@endpush

<div class="view-header">
  @include('partials.header-actions')
</div>

<a href="{{ route('members.index') }}" class="members-back"><i class="fa-solid fa-arrow-left"></i> Tilbage til medlemmer</a>

<div class="card">
  <div class="member-hero">
    @include('partials.avatar', ['u' => $member, 'size' => 'xl'])
    <div class="info">
      <div class="name">{{ $member->name }} @if ($isSelf)<span style="color:var(--accent);font-size:14px;font-weight:600;margin-left:6px;">(dig)</span>@endif</div>
      <div class="role">
        @php
          $roleLabel = match ($member->role) {
            'trainer' => 'Træner',
            'assistant' => 'Assistent',
            default => 'Medlem',
          };
        @endphp
        {{ $roleLabel }}
      </div>
    </div>
    <div class="actions">
      @if ($isSelf)
        <a href="{{ route('profile.edit') }}" class="btn btn-secondary btn-sm"><i class="fa-solid fa-pen"></i> Rediger profil</a>
      @else
        <a href="{{ route('beskeder.index', ['til' => $member->id]) }}" class="btn btn-primary btn-sm"><i class="fa-regular fa-envelope"></i> Send besked</a>
      @endif
    </div>
  </div>

  @if (trim((string) $member->about) !== '')
    <div class="member-section">
      <h2>Om</h2>
      <div class="about">{{ $member->about }}</div>
    </div>
  @endif

  @if ($trainerCourses->isNotEmpty())
    <div class="member-section">
      <h2>Underviser på</h2>
      <div class="course-list">
        @foreach ($trainerCourses as $c)
          <a href="{{ route('courses.show', $c) }}">
            @if ($c->image_path)<img src="{{ $c->imageUrl() }}" alt="">@else<div class="ph"><i class="fa-solid fa-chalkboard-user"></i></div>@endif
            <div>
              <div class="t">{{ $c->title }}</div>
              <div class="sub">{{ $c->activeCount() }}/{{ $c->max_participants }} tilmeldte @if ($c->scheduleLabel()) · {{ $c->scheduleLabel() }}@endif</div>
            </div>
          </a>
        @endforeach
      </div>
    </div>
  @endif

  @if ($enrolledCourses->isNotEmpty())
    <div class="member-section">
      <h2>Tilmeldt på</h2>
      <div class="course-list">
        @foreach ($enrolledCourses as $c)
          <a href="{{ route('courses.show', $c) }}">
            @if ($c->image_path)<img src="{{ $c->imageUrl() }}" alt="">@else<div class="ph"><i class="fa-solid fa-dumbbell"></i></div>@endif
            <div>
              <div class="t">{{ $c->title }}</div>
              <div class="sub">{{ count($c->trainers) === 1 ? 'Træner' : 'Trænere' }} {{ $c->trainerNames() }} @if ($c->scheduleLabel()) · {{ $c->scheduleLabel() }}@endif</div>
            </div>
          </a>
        @endforeach
      </div>
    </div>
  @endif

  @if ($enrolledCourses->isEmpty() && $trainerCourses->isEmpty() && trim((string) $member->about) === '')
    <div class="member-section">
      <div class="empty-line">{{ $isSelf ? 'Du har ikke tilføjet noget om dig selv endnu.' : 'Ingen aktivitet endnu.' }}</div>
    </div>
  @endif
</div>

@endsection
