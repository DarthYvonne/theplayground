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
    /* Hard-lock the page so the keyboard can't scroll the body underneath.
       Without this the browser scrolls the focused input into view by
       scrolling the body, leaving a gap above the fixed bottom tab bar. */
    html, body { height: 100%; height: 100dvh; overflow: hidden; overscroll-behavior: none; }
    .app { min-height: 0; height: 100%; }

    /* Default: reserve the actual rendered tab-bar height (set by JS). */
    .main { padding-bottom: var(--tabbar-h, 52px); }
    .chat-composer { padding-bottom: 8px; }

    /* Keyboard open: drop the tab bar entirely (it'd be behind the keyboard
       or floating above it) and bottom-pad by the keyboard height. On
       Chromes where the layout viewport shrinks ("resizes-content" mode)
       --kb-h stays 0 because dvh already shrank — padding-bottom: 0 is then
       correct, and the composer ends right at the keyboard top. */
    body.kb-open .course-tabs { display: none; }
    body.kb-open .main { padding-bottom: var(--kb-h, 0px); }
  }
</style>
@endpush

@push('scripts')
<script>
(function () {
  /* Tell Chrome (and any UA that honors it) to shrink the LAYOUT viewport
     when the on-screen keyboard opens, so 100dvh reacts to the keyboard.
     iOS Safari ignores this; the visualViewport branch below handles that.
     Scoped to this page — new navigations re-render the layout's meta tag. */
  var vp = document.querySelector('meta[name="viewport"]');
  if (vp && !/interactive-widget/.test(vp.content)) {
    vp.setAttribute('content', vp.content + ', interactive-widget=resizes-content');
  }

  var bar = document.querySelector('.course-tabs');
  var vv = window.visualViewport;
  var input = document.querySelector('.chat-composer input');

  function syncBar() {
    if (bar && getComputedStyle(bar).position === 'fixed') {
      document.documentElement.style.setProperty('--tabbar-h', bar.offsetHeight + 'px');
    } else {
      document.documentElement.style.removeProperty('--tabbar-h');
    }
  }

  function syncKb() {
    var kb = 0;
    if (vv) {
      /* Visual-viewport diff catches iOS Safari and any browser in
         "resizes-visual" mode. On "resizes-content" the diff is 0 — fine,
         dvh has already shrunk so we just need to drop the tab bar. */
      kb = Math.max(0, window.innerHeight - vv.height - vv.offsetTop);
    }
    document.documentElement.style.setProperty('--kb-h', kb + 'px');
    /* Treat the keyboard as open if either the visual diff is significant
       or the chat input is focused (covers resizes-content where the diff
       stays 0 but the keyboard is up). */
    var focused = input && document.activeElement === input;
    document.body.classList.toggle('kb-open', kb > 80 || focused);
  }

  syncBar();
  syncKb();
  window.addEventListener('resize', function () { syncBar(); syncKb(); });
  window.addEventListener('orientationchange', function () { syncBar(); syncKb(); });
  if (vv) {
    vv.addEventListener('resize', syncKb);
    vv.addEventListener('scroll', syncKb);
  }
  if (input) {
    input.addEventListener('focus', syncKb);
    input.addEventListener('blur', syncKb);
  }
})();
</script>
@endpush

@endsection
