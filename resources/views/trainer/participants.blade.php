@extends('layouts.app')
@section('content')

<div class="view-header">
  <h1>
    <a href="{{ route('trainer.index') }}" style="color:inherit;"><i class="fa-solid fa-arrow-left" style="font-size:16px;margin-right:8px;"></i></a>
    Roster — {{ $course->title }}
  </h1>
  @include('partials.header-actions')
</div>

<div class="card" style="overflow:hidden;">
  @if ($enrollments->isEmpty())
    <div class="card-pad" style="text-align:center;color:var(--muted);">No active members yet.</div>
  @else
    @foreach ($enrollments as $e)
      <div style="display:flex;gap:12px;align-items:center;padding:12px 16px;border-top:1px solid #f0f2f5;">
        @include('partials.avatar', ['u' => $e->user])
        <div style="flex:1;min-width:0;">
          <div style="font-weight:700;">{{ $e->user->name }}</div>
          <div style="color:var(--muted);font-size:13px;">{{ $e->user->email }} @if ($e->user->phone) · {{ $e->user->phone }} @endif</div>
        </div>
        <div style="color:var(--muted);font-size:12px;">since {{ $e->enrolled_at?->diffForHumans() }}</div>
      </div>
    @endforeach
  @endif
</div>

@endsection
