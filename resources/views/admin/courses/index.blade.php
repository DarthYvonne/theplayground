@extends('layouts.app')
@section('content')

@push('styles')
<style>
  .subnav-row { position: relative; }
  .subnav-row .subnav-action { position: absolute; top: 0; right: 0; }
  @media (max-width: 540px) {
    .subnav-row .subnav-action { position: static; display: inline-flex; margin-bottom: 12px; }
  }
</style>
@endpush

<div class="view-header">
  <h1>Hold</h1>
  @include('partials.header-actions')
</div>

<div class="subnav-row">
  @include('admin.courses._subnav')
  <a href="{{ route('admin.courses.create') }}" class="btn btn-primary subnav-action"><i class="fa-solid fa-plus"></i> Nyt hold</a>
</div>

@if ($courses->isEmpty())
  <div class="card card-pad" style="text-align:center;color:var(--muted);">
    Ingen hold endnu. <a href="{{ route('admin.courses.create') }}" style="color:var(--accent);font-weight:600;">Opret det første →</a>
  </div>
@else
  <div class="course-grid">
    @foreach ($courses as $c)
      <div class="card course-tile">
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
          <div class="course-tile-actions">
            <a href="{{ route('admin.courses.edit', $c) }}" class="btn btn-primary btn-sm"><i class="fa-solid fa-pen"></i> Rediger</a>
            <a href="{{ route('trainer.participants', $c) }}" class="btn btn-secondary btn-sm"><i class="fa-solid fa-users"></i> Deltagere</a>
          </div>
        </div>
      </div>
    @endforeach
  </div>
@endif

@endsection
