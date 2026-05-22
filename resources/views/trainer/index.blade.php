@extends('layouts.app')
@section('content')

<div class="view-header">
  <h1>Mine hold</h1>
  @include('partials.header-actions')
</div>

@include('trainer._subnav')

@if ($courses->isEmpty())
  <div class="card card-pad" style="text-align:center;color:var(--muted);">Du er ikke tilknyttet nogen hold endnu.</div>
@else
  <div class="course-grid">
    @foreach ($courses as $c)
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
            <a href="{{ route('trainer.broadcast', $c) }}" class="btn btn-secondary btn-sm"><i class="fa-regular fa-envelope"></i> Skriv</a>
            <a href="{{ route('trainer.participants', $c) }}" class="btn btn-secondary btn-sm"><i class="fa-solid fa-users"></i> Deltagere</a>
          </div>
        </div>
      </div>
    @endforeach
  </div>
@endif

@endsection
