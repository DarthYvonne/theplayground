@extends('layouts.app')
@section('content')

@push('styles')
<style>
  a.course-tile { color: inherit; text-decoration: none; transition: transform 0.1s, box-shadow 0.1s; }
  a.course-tile:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(0,0,0,0.08); }
  .course-tile-title { font-size: 18px; line-height: 1.25; }
</style>
@endpush

<div class="view-header">
  <h1>Hold du træner</h1>
  @include('partials.header-actions')
</div>

@include('trainer._subnav')

@if ($courses->isEmpty())
  <div class="card card-pad" style="text-align:center;color:var(--muted);">Du er ikke tilknyttet nogen hold endnu.</div>
@else
  <div class="course-grid">
    @foreach ($courses as $c)
      <a href="{{ route('courses.show', $c) }}" class="card course-tile" aria-label="{{ $c->title }}">
        @if ($c->image_path)
          <img src="{{ $c->imageUrl() }}" alt="" class="course-tile-img">
        @else
          <div class="course-tile-img course-tile-img-ph"><i class="fa-solid fa-chalkboard-user"></i></div>
        @endif
        <div class="card-pad">
          <div class="course-tile-title">{{ $c->title }}</div>
          <div class="course-tile-meta">{{ $c->activeCount() }}/{{ $c->max_participants }} tilmeldte · {{ $c->is_active ? 'Aktiv' : 'Kladde' }}</div>
          @if ($c->scheduleLabel())
            <div class="course-tile-meta" style="margin-top:4px;"><i class="fa-regular fa-clock" style="margin-right:4px;"></i>{{ $c->scheduleLabel() }}</div>
          @endif
        </div>
      </a>
    @endforeach
  </div>
@endif

@endsection
