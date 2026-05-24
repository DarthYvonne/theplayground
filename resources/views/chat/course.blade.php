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
  .main { display: flex; flex-direction: column; min-height: 100vh; min-height: 100dvh; }
  .chat-shell { flex: 1; display: flex; flex-direction: column; min-height: 0; }
  .chat-card { flex: 1; height: auto; min-height: 0; }

  @media (max-width: 767px) {
    .chat-composer { padding-bottom: 8px; }
  }
</style>
@endpush

@endsection
