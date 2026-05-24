@extends('layouts.app')
@section('content')

<div class="view-header">
  <h1>
    <a href="{{ route('courses.show', $course) }}" style="color:inherit;"><i class="fa-solid fa-arrow-left" style="font-size:16px;margin-right:8px;"></i></a>
    {{ $course->title }}
  </h1>
  @include('partials.header-actions')
</div>

@include('partials.course-tabs')

@include('chat._room', [
  'title' => $course->title,
  'sub' => 'Hold-chat · ' . $course->activeCount() . ' deltagere · træner ' . $course->trainer->name,
  'icon' => 'fa-regular fa-comments',
  'listUrl' => url('/api/chat/courses/' . $course->id),
  'sendUrl' => url('/api/chat/courses/' . $course->id),
  'showHead' => false,
])

{{-- This @push must come AFTER the @include above so its rules win
     over _room.blade.php's pushed styles. Instead of subtracting magic
     numbers, we make .main a flex-column so the chat-card grows to
     fill whatever vertical space is left. --}}
@push('styles')
<style>
  /* Viewport-locked chat layout (desktop & mobile):
       top    — view-header (pinned)
       middle — chat-stream (flex-grow, scrollable)
       above bottom — chat-composer (pinned)
       bottom — course-tabs (pinned) */
  .main {
    display: flex; flex-direction: column;
    height: 100vh; height: 100dvh;
    padding-bottom: 0;
    overflow: hidden;
  }
  .main > * { flex-shrink: 0; }
  /* Desktop: view-header → course-tabs → chat-shell (DOM order).
     Mobile: course-tabs is position:fixed at bottom, so order doesn't apply. */
  .view-header { order: 0; }
  .course-tabs { order: 0; }
  .chat-shell { order: 1; flex: 1; min-height: 0; display: flex; flex-direction: column; }

  .chat-card { flex: 1; height: auto; min-height: 0; }

  @media (max-width: 767px) {
    /* course-tabs is position:fixed on mobile — reserve just its actual
       height in main's bottom padding so the composer ends right above it. */
    .main { padding-bottom: calc(52px + env(safe-area-inset-bottom)); }
    .chat-composer { padding-bottom: 8px; }
  }
</style>
@endpush

@endsection
