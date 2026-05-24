@extends('layouts.app')
@section('content')

<div class="view-header">
  <h1><i class="fa-solid fa-dumbbell" style="color:var(--text);margin-right:8px;"></i>Hold</h1>
  <a href="{{ route('admin.courses.create') }}" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Nyt hold</a>
</div>

@include('admin.courses._subnav')

@push('styles')
<style>
  a.course-tile { color: inherit; text-decoration: none; transition: transform 0.1s, box-shadow 0.1s; }
  a.course-tile:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(0,0,0,0.08); }
  .course-tile-title { font-size: 18px; line-height: 1.25; }
</style>
@endpush

@if ($courses->isEmpty())
  <div class="card card-pad" style="text-align:center;color:var(--muted);">
    Ingen hold endnu. <a href="{{ route('admin.courses.create') }}" style="color:var(--accent);font-weight:600;">Opret det første →</a>
  </div>
@else
  <div class="course-grid">
    @foreach ($courses as $c)
      <a href="{{ route('courses.show', $c) }}" class="card course-tile" aria-label="{{ $c->title }}">
        @if ($c->image_path)
          <img src="{{ $c->imageUrl() }}" alt="" class="course-tile-img">
        @else
          <div class="course-tile-img course-tile-img-ph"><i class="fa-solid fa-dumbbell"></i></div>
        @endif
        <div class="card-pad">
          <div class="course-tile-title">{{ $c->title }}</div>
          <div class="course-tile-meta">
            {{ $c->active_enrollments_count }}/{{ $c->max_participants }} tilmeldte ·
            {{ $c->price() }} ·
            @if ($c->is_active)<span style="color:#166534;font-weight:600;">Aktiv</span>@else<span>Kladde</span>@endif
          </div>
          <div class="course-tile-meta" style="margin-top:4px;"><i class="fa-regular fa-user" style="margin-right:4px;"></i>{{ $c->trainer->name }}</div>
        </div>
      </a>
    @endforeach
  </div>
@endif

@endsection
