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
    padding-bottom: 15px;
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
    /* course-tabs is position:fixed on mobile — reserve its actual rendered
       height (set by JS below) so the composer ends right at the tab bar.
       The fallback 52px is just a sensible default before the script runs. */
    .main { padding-bottom: var(--tabbar-h, 52px); }
    .chat-composer { padding-bottom: 8px; }
  }
</style>
@endpush

@push('scripts')
<script>
(function () {
  var bar = document.querySelector('.course-tabs');
  if (!bar) return;
  function sync() {
    /* Only set the var when the bar is actually rendered as the fixed bottom
       bar (mobile breakpoint). On desktop the tabs are inline, so leave it.  */
    if (getComputedStyle(bar).position === 'fixed') {
      document.documentElement.style.setProperty('--tabbar-h', bar.offsetHeight + 'px');
    } else {
      document.documentElement.style.removeProperty('--tabbar-h');
    }
  }
  sync();
  window.addEventListener('resize', sync);
  window.addEventListener('orientationchange', sync);
})();
</script>
@endpush

@endsection
