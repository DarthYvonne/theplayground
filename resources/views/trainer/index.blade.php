@extends('layouts.app')
@section('content')

<div class="view-header">
  <h1>Trainer hub</h1>
  @include('partials.header-actions')
</div>

@if ($courses->isEmpty())
  <div class="card card-pad" style="text-align:center;color:var(--muted);">You're not assigned to any courses yet.</div>
@else
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:14px;">
    @foreach ($courses as $c)
      <div class="card">
        @if ($c->image_path)
          <img src="{{ $c->imageUrl() }}" alt="" style="width:100%;height:140px;object-fit:cover;">
        @else
          <div style="width:100%;height:140px;background:linear-gradient(135deg,var(--accent-soft),#f5f7fb);display:flex;align-items:center;justify-content:center;color:var(--accent);font-size:40px;"><i class="fa-solid fa-chalkboard-user"></i></div>
        @endif
        <div class="card-pad">
          <div style="font-weight:700;">{{ $c->title }}</div>
          <div style="color:var(--muted);font-size:13px;margin-top:2px;">{{ $c->activeCount() }}/{{ $c->max_participants }} enrolled · {{ $c->is_active ? 'Active' : 'Draft' }}</div>
          <div style="display:flex;gap:6px;margin-top:12px;flex-wrap:wrap;">
            <a href="{{ route('chat.course', $c) }}" class="btn btn-primary btn-sm"><i class="fa-regular fa-comments"></i> Chat</a>
            <a href="{{ route('trainer.broadcast', $c) }}" class="btn btn-secondary btn-sm"><i class="fa-regular fa-envelope"></i> Email</a>
            <a href="{{ route('trainer.participants', $c) }}" class="btn btn-secondary btn-sm"><i class="fa-solid fa-users"></i> Roster</a>
          </div>
        </div>
      </div>
    @endforeach
  </div>
@endif

@endsection
