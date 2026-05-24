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
     over _room.blade.php's pushed styles. The bottom tab bar takes a
     chunk of the viewport on mobile and pushes the desktop tab strip
     further down, so the default height calc is too tall on both. --}}
@push('styles')
<style>
  /* Desktop: extra room for the inline tab strip above the chat card. */
  .chat-card {
    height: calc(100vh - 220px);
    height: calc(100dvh - 220px);
    min-height: 360px;
  }

  /* Mobile: chat-card fills the space between view-header and the fixed
     bottom tab bar. Chrome budget: main padding-top (70) + view-header h1 (~25)
     + view-header margin-bottom (18) - chat-card negative top margin (14)
     + bottom tab bar content (~51) = ~150. Tab bar itself absorbs
     env(safe-area-inset-bottom), so we add that as well. */
  @media (max-width: 767px) {
    .chat-card {
      height: calc(100vh - 150px - env(safe-area-inset-bottom));
      height: calc(100dvh - 150px - env(safe-area-inset-bottom));
      min-height: 0;
    }
    .chat-composer { padding-bottom: 8px; }
  }
</style>
@endpush

@endsection
