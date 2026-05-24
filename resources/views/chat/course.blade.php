@extends('layouts.app')
@section('content')

@push('styles')
<style>
  /* Leave room for the fixed bottom tab bar on mobile. */
  @media (max-width: 767px) {
    .chat-card {
      height: calc(100vh - 56px - 70px - env(safe-area-inset-bottom));
      height: calc(100dvh - 56px - 70px - env(safe-area-inset-bottom));
    }
  }
</style>
@endpush

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
])

@endsection
