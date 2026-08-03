@extends('layouts.app')
@section('content')

@push('styles')
<style>
  .besk-thread { max-width: 720px; }

  .besk-header-id { display: inline-flex; align-items: center; gap: 10px; color: inherit; }
  .besk-header-back { color: var(--muted); font-size: 18px; padding: 4px 8px; border-radius: 6px; margin-right: 4px; }
  .besk-header-back:hover { background: var(--hover); color: var(--text); }

  .thread-head-mobile { display: none; }

  /* Tinted, so a white bubble has something to sit on. When the stream was
     also #fff the incoming bubbles had nothing but a 5%-alpha shadow to
     separate them from the panel, and effectively disappeared. */
  .thread-stream { background: #f5f7fa; border-radius: 12px 12px 0 0; padding: 16px 16px 10px; display: flex; flex-direction: column; gap: 2px; min-height: 300px; box-shadow: 0 1px 2px rgba(0,0,0,0.06); }
  .thread-stream .empty { margin: auto; text-align: center; color: var(--muted); padding: 40px 20px; }
  .thread-stream .empty i { font-size: 30px; color: #c8cfd8; display: block; margin-bottom: 10px; }

  .dmsg { display: flex; gap: 8px; align-items: flex-start; max-width: min(80%, 560px); }
  .dmsg.mine { align-self: flex-end; }
  /* Top of a run carries the avatar; the rest hold the column open. */
  .dmsg .av-slot { width: 32px; flex-shrink: 0; }
  /* Optically nudged down: a circle flush with the bubble's square top edge
     reads as sitting too high, because its mass starts below its bounding box. */
  .dmsg > .av { margin-top: 5px; }
  .dmsg .dmsg-body { display: flex; flex-direction: column; align-items: flex-start; min-width: 0; }
  .dmsg.mine .dmsg-body { align-items: flex-end; }
  .dmsg.run-end { margin-bottom: 10px; }

  .dmsg .bubble { background: #fff; border: 1px solid #e3e7ee; box-shadow: 0 1px 2px rgba(16,24,40,0.05); padding: 9px 14px; border-radius: 18px; line-height: 1.45; overflow-wrap: anywhere; white-space: pre-wrap; max-width: 100%; }
  .dmsg.mine .bubble { background: var(--accent); border-color: var(--accent); color: #fff; box-shadow: 0 1px 2px rgba(24,119,242,0.28); }
  /* Flatten the inner corners so a run of bubbles reads as one block. */
  .dmsg:not(.mine):not(.run-start) .bubble { border-top-left-radius: 6px; }
  .dmsg:not(.mine):not(.run-end) .bubble { border-bottom-left-radius: 6px; }
  .dmsg.mine:not(.run-start) .bubble { border-top-right-radius: 6px; }
  .dmsg.mine:not(.run-end) .bubble { border-bottom-right-radius: 6px; }

  .dmsg .time { font-size: 11px; color: var(--muted); margin: 3px 12px 0; }
  .dmsg .time .seen { color: var(--accent); margin-left: 4px; }
  .dmsg .time .seen i { margin-right: 3px; }

  .day-sep { display: flex; align-items: center; gap: 10px; margin: 12px 0 14px; }
  .day-sep::before, .day-sep::after { content: ''; flex: 1; height: 1px; background: #e3e7ed; }
  .day-sep span { color: var(--muted); font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.3px; }
  .day-sep:first-child { margin-top: 0; }

  .thread-reply { background: #fff; border-top: 1px solid #e8ebf0; border-radius: 0 0 12px 12px; padding: 12px 14px; display: flex; gap: 8px; align-items: flex-end; }
  .thread-reply textarea { flex: 1; border: 1px solid transparent; background: #f0f2f5; border-radius: 20px; padding: 10px 16px; font-family: inherit; font-size: 15px; line-height: 1.4; min-height: 40px; max-height: 140px; resize: none; transition: background 0.12s, border-color 0.12s; }
  .thread-reply textarea:focus { background: #fff; border-color: var(--accent); box-shadow: 0 0 0 3px rgba(24,119,242,0.15); }
  .thread-reply button { background: var(--accent); color: #fff; border: none; border-radius: 50%; width: 40px; height: 40px; padding: 0; cursor: pointer; font-size: 15px; flex-shrink: 0; display: inline-flex; align-items: center; justify-content: center; transition: background 0.12s, transform 0.12s; }
  .thread-reply button:hover { background: var(--accent-hover); }
  .thread-reply button:active { transform: scale(0.92); }

  @media (max-width: 767px) {
    /* Hard-lock the page so the keyboard can't scroll the body underneath. */
    html, body { height: 100%; height: 100dvh; overflow: hidden; overscroll-behavior: none; }
    .app { min-height: 0; height: 100%; }
    .main { padding: 0; }

    /* Pin the thread to the visible area between the mobile-topbar (56px)
       and the bottom of the viewport (or the keyboard, when open).
       Override .main > * { max-width: 720px; margin-right: auto; } so it
       spans edge-to-edge instead of being squeezed into the content column. */
    .besk-thread {
      position: fixed;
      top: 56px; left: 0; right: 0;
      bottom: var(--kb-h, 0px);
      max-width: none; margin: 0;
      display: flex; flex-direction: column;
    }

    .thread-head-mobile { display: flex; gap: 10px; align-items: center; padding: 12px 16px; background: #fff; border-bottom: 1px solid #f0f2f5; color: var(--text); flex-shrink: 0; }
    .thread-head-mobile .name { font-weight: 700; font-size: 15px; }

    .thread-stream { flex: 1; min-height: 0; overflow-y: auto; border-radius: 0; box-shadow: none; }
    .thread-reply { border-radius: 0; padding-bottom: max(12px, env(safe-area-inset-bottom)); flex-shrink: 0; }
    /* iOS zooms inputs with font-size < 16px on focus. */
    .thread-reply textarea { font-size: 16px; }
  }
</style>
@endpush

<div class="view-header">
  <h1>
    <a href="{{ route('beskeder.index') }}" class="besk-header-back" title="Tilbage til Beskeder"><i class="fa-solid fa-arrow-left"></i></a>
    <a href="{{ route('members.show', $other) }}" class="besk-header-id">
      @include('partials.avatar', ['u' => $other, 'size' => 'sm'])
      <span>{{ $other->name }}</span>
    </a>
  </h1>
  @include('partials.header-actions')
</div>

<div class="besk-thread">
  <a href="{{ route('members.show', $other) }}" class="thread-head-mobile">
    @include('partials.avatar', ['u' => $other, 'size' => 'sm'])
    <span class="name">{{ $other->name }}</span>
  </a>
  <div class="thread-stream" id="threadStream">
    @if ($messages->isEmpty())
      <div class="empty"><i class="fa-regular fa-comments"></i>Ingen beskeder endnu — skriv den første.</div>
    @else
      @php $list = $messages->values(); $lastDay = null; @endphp
      @foreach ($list as $i => $m)
        @php
          $prev = $i > 0 ? $list[$i - 1] : null;
          $next = $list->get($i + 1);
          $mine = $m->sender_id === auth()->id();
          $day = $m->created_at->format('Y-m-d');
          $newDay = $day !== $lastDay;
          // A run is the same sender, same day, within 5 minutes. Compared on
          // raw timestamps so the sign of Carbon's diff() can't bite us.
          $startsRun = $newDay || !$prev || $prev->sender_id !== $m->sender_id
            || $m->created_at->getTimestamp() - $prev->created_at->getTimestamp() > 300;
          $endsRun = !$next || $next->sender_id !== $m->sender_id
            || $next->created_at->format('Y-m-d') !== $day
            || $next->created_at->getTimestamp() - $m->created_at->getTimestamp() > 300;
        @endphp
        @if ($newDay)
          <div class="day-sep"><span>{{ $m->created_at->isToday() ? 'I dag' : ($m->created_at->isYesterday() ? 'I går' : $m->created_at->translatedFormat('j. F Y')) }}</span></div>
          @php $lastDay = $day; @endphp
        @endif
        <div class="dmsg {{ $mine ? 'mine' : '' }} {{ $startsRun ? 'run-start' : '' }} {{ $endsRun ? 'run-end' : '' }}">
          @unless ($mine)
            @if ($startsRun)
              @include('partials.avatar', ['u' => $other, 'size' => 'sm'])
            @else
              <div class="av-slot"></div>
            @endif
          @endunless
          <div class="dmsg-body">
            <div class="bubble">{{ $m->body }}</div>
            {{-- One clock per run. "via Hold-besked" is per-message, so a
                 message carrying it always gets its own line. --}}
            @if ($endsRun || $m->viaCourse)
              <div class="time">
                {{ $m->created_at->format('H:i') }}
                @if ($m->viaCourse)
                  · <i class="fa-solid fa-bullhorn" title="Sendt via Hold-besked"></i> {{ $m->viaCourse->title }}
                @endif
                @if ($mine && $m->read_at)
                  <span class="seen" title="Set {{ $m->read_at->translatedFormat('j. F H:i') }}">
                    <i class="fa-solid fa-check-double"></i>Set {{ $m->read_at->isToday() ? $m->read_at->format('H:i') : $m->read_at->translatedFormat('j. M H:i') }}
                  </span>
                @endif
              </div>
            @endif
          </div>
        </div>
      @endforeach
    @endif
  </div>

  <form method="POST" action="{{ route('beskeder.store') }}" class="thread-reply" id="replyForm">
    @csrf
    <input type="hidden" name="recipient_users[]" value="{{ $other->id }}">
    <textarea name="body" id="replyBody" placeholder="Skriv et svar…" maxlength="8000" required autofocus></textarea>
    <button type="submit" aria-label="Send"><i class="fa-solid fa-paper-plane"></i></button>
  </form>
</div>

@push('scripts')
<script>
(function () {
  var stream = document.getElementById('threadStream');
  if (stream) stream.scrollTop = stream.scrollHeight;

  var ta = document.getElementById('replyBody');
  function autosize() { ta.style.height = 'auto'; ta.style.height = Math.min(ta.scrollHeight, 140) + 'px'; }
  ta.addEventListener('input', autosize);
  ta.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      document.getElementById('replyForm').requestSubmit();
    }
  });

  /* Mobile: track keyboard height so the thread (position:fixed) can pin
     to the keyboard top instead of being pushed under it. Mirror of the
     logic in chat/course.blade.php. */
  var vpMeta = document.querySelector('meta[name="viewport"]');
  if (vpMeta && !/interactive-widget/.test(vpMeta.content)) {
    vpMeta.setAttribute('content', vpMeta.content + ', interactive-widget=resizes-content');
  }
  var vv = window.visualViewport;
  function syncKb() {
    var kb = 0;
    if (vv) kb = Math.max(0, window.innerHeight - vv.height - vv.offsetTop);
    document.documentElement.style.setProperty('--kb-h', kb + 'px');
    if (stream) stream.scrollTop = stream.scrollHeight;
  }
  syncKb();
  window.addEventListener('resize', syncKb);
  window.addEventListener('orientationchange', syncKb);
  if (vv) { vv.addEventListener('resize', syncKb); vv.addEventListener('scroll', syncKb); }
  ta.addEventListener('focus', syncKb);
  ta.addEventListener('blur', syncKb);

  // Mobile: turn burger into back-to-Beskeder, set topbar title to "Beskeder"
  var toggle = document.getElementById('sidebarToggle');
  var titleEl = document.getElementById('topbarTitle');
  if (toggle && titleEl) {
    var originalToggleHtml = toggle.innerHTML;
    var BACK_URL = '{{ route('beskeder.index') }}';
    function goBack(e) {
      e.preventDefault();
      e.stopImmediatePropagation();
      window.location.href = BACK_URL;
    }
    var mql = window.matchMedia('(max-width: 767px)');
    function apply(matches) {
      if (matches) {
        toggle.innerHTML = '<i class="fa-solid fa-arrow-left"></i>';
        toggle.setAttribute('aria-label', 'Tilbage til Beskeder');
        toggle.addEventListener('click', goBack, true);
        titleEl.textContent = 'Beskeder';
      } else {
        toggle.innerHTML = originalToggleHtml;
        toggle.setAttribute('aria-label', 'Menu');
        toggle.removeEventListener('click', goBack, true);
      }
    }
    apply(mql.matches);
    mql.addEventListener('change', function (e) { apply(e.matches); });
  }
})();
</script>
@endpush

@endsection
