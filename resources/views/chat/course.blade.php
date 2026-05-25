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
  'sub' => 'Hold-chat · ' . $course->activeCount() . ' deltagere · ' . (count($course->trainers) === 1 ? 'træner' : 'trænere') . ' ' . $course->trainerNames(),
  'icon' => 'fa-regular fa-comments',
  'listUrl' => url('/api/chat/courses/' . $course->id),
  'sendUrl' => url('/api/chat/courses/' . $course->id),
  'showHead' => false,
])

{{-- This @push must come AFTER the @include above so its rules win
     over _room.blade.php's pushed styles. --}}
@push('styles')
<style>
  /* Desktop: chat-card grows to fill remaining vertical space inside .main. */
  .main {
    display: flex; flex-direction: column;
    height: 100vh; height: 100dvh;
    padding-bottom: 15px;
    overflow: hidden;
  }
  .main > * { flex-shrink: 0; }
  .chat-shell { flex: 1; min-height: 0; display: flex; flex-direction: column; }
  .chat-card { flex: 1; height: auto; min-height: 0; }

  @media (max-width: 767px) {
    /* Hard-lock the page so the keyboard can't scroll the body underneath. */
    html, body { height: 100%; height: 100dvh; overflow: hidden; overscroll-behavior: none; }
    .app { min-height: 0; height: 100%; }

    /* Drop the desktop flex-column trick on mobile — we pin chat-shell
       to the viewport directly, so .main doesn't need to participate. */
    .main { display: block; height: auto; overflow: visible; padding: 0; }

    /* Pin the chat to the visible area between the mobile-topbar (56px)
       and the bottom course-tabs. Override .main > * { max-width: 720px;
       margin-right: auto; } so it actually spans edge-to-edge instead of
       being squeezed into the left-aligned 720px content column. */
    .chat-shell {
      position: fixed;
      top: 56px; left: 0; right: 0;
      bottom: var(--tabbar-h, 52px);
      max-width: none; margin: 0;
      display: flex; flex-direction: column;
    }
    /* Keyboard open: tab bar would be behind the keyboard — hide it and
       let the chat fill down to the keyboard top. */
    body.kb-open .course-tabs { display: none; }
    body.kb-open .chat-shell { bottom: var(--kb-h, 0px); }

    .chat-card { flex: 1; height: auto; min-height: 0; margin: 0; border-radius: 0; box-shadow: none; }
    .chat-composer { padding-bottom: max(8px, env(safe-area-inset-bottom)); }
    /* iOS zooms inputs with font-size < 16px on focus — that's what
       causes the page to "jump around" when you tap the input. */
    .chat-composer textarea { font-size: 16px; }
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
         "resizes-visual" mode. On "resizes-content" the diff is 0 — that's
         fine because the layout viewport already shrank, so dvh and any
         fixed-bottom elements sit above the keyboard on their own. */
      kb = Math.max(0, window.innerHeight - vv.height - vv.offsetTop);
    }
    document.documentElement.style.setProperty('--kb-h', kb + 'px');
    document.body.classList.toggle('kb-open', kb > 80);
  }

  syncBar();
  syncKb();
  window.addEventListener('resize', function () { syncBar(); syncKb(); });
  window.addEventListener('orientationchange', function () { syncBar(); syncKb(); });
  if (vv) {
    vv.addEventListener('resize', syncKb);
    vv.addEventListener('scroll', syncKb);
  }
})();
</script>
@endpush

@endsection
