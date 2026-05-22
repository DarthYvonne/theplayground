@extends('layouts.app')
@section('content')

@push('styles')
<style>
  .courses-table { background: #fff; border-radius: 12px; box-shadow: 0 1px 2px rgba(0,0,0,0.08); overflow: hidden; }
  .courses-table .row { display: grid; grid-template-columns: 70px 1fr 140px 110px 90px 110px; gap: 12px; align-items: center; padding: 12px 14px; border-top: 1px solid #f0f2f5; }
  .courses-table .row:first-child { border-top: none; background: #fafbfc; font-size: 12px; text-transform: uppercase; letter-spacing: 0.3px; color: var(--muted); padding: 10px 14px; font-weight: 700; }
  .courses-table img, .courses-table .thumb-ph { width: 56px; height: 56px; border-radius: 8px; object-fit: cover; background: var(--accent-soft); display: flex; align-items: center; justify-content: center; color: var(--accent); font-size: 20px; }
  .courses-table .t { font-weight: 700; }
  .courses-table .sub { color: var(--muted); font-size: 12px; margin-top: 2px; }
  @media (max-width: 767px) {
    .courses-table .row { grid-template-columns: 56px 1fr auto; }
    .courses-table .col-hide-mobile { display: none; }
  }
</style>
@endpush

<div class="view-header">
  <h1>Hold</h1>
  <a href="{{ route('admin.courses.create') }}" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Nyt hold</a>
  @include('partials.header-actions')
</div>

@if ($courses->isEmpty())
  <div class="card card-pad" style="text-align:center;color:var(--muted);">
    Ingen hold endnu. <a href="{{ route('admin.courses.create') }}" style="color:var(--accent);font-weight:600;">Opret det første →</a>
  </div>
@else
  <div class="courses-table">
    <div class="row">
      <div></div>
      <div>Titel</div>
      <div class="col-hide-mobile">Træner</div>
      <div class="col-hide-mobile">Tilmeldte</div>
      <div class="col-hide-mobile">Status</div>
      <div></div>
    </div>
    @foreach ($courses as $c)
      <div class="row">
        @if ($c->image_path)<img src="{{ $c->imageUrl() }}" alt="">@else<div class="thumb-ph"><i class="fa-solid fa-dumbbell"></i></div>@endif
        <div>
          <a class="t" href="{{ route('admin.courses.edit', $c) }}">{{ $c->title }}</a>
          <div class="sub">{{ $c->price() }} · maks. {{ $c->max_participants }}</div>
        </div>
        <div class="col-hide-mobile">{{ $c->trainer->name }}</div>
        <div class="col-hide-mobile">{{ $c->active_enrollments_count }}/{{ $c->max_participants }}</div>
        <div class="col-hide-mobile">
          @if ($c->is_active)<span class="tag success">Aktiv</span>@else<span class="tag muted">Kladde</span>@endif
        </div>
        <div style="display:flex;gap:6px;justify-content:flex-end;">
          <a href="{{ route('admin.courses.edit', $c) }}" class="btn btn-secondary btn-sm"><i class="fa-solid fa-pen"></i></a>
        </div>
      </div>
    @endforeach
  </div>
@endif

@endsection
