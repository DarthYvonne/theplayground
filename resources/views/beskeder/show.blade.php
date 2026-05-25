@extends('layouts.app')
@section('content')

@push('styles')
<style>
  .besk-thread { max-width: 720px; }

  .besk-header-id { display: inline-flex; align-items: center; gap: 10px; color: inherit; }
  .besk-header-back { color: var(--muted); font-size: 18px; padding: 4px 8px; border-radius: 6px; margin-right: 4px; }
  .besk-header-back:hover { background: var(--hover); color: var(--text); }

  .thread-head-mobile { display: none; }

  .thread-stream { background: #fff; border-radius: 12px 12px 0 0; padding: 16px; display: flex; flex-direction: column; gap: 8px; min-height: 300px; box-shadow: 0 1px 2px rgba(0,0,0,0.06); }
  .thread-stream .empty { text-align: center; color: var(--muted); padding: 40px 20px; }

  .dmsg { display: flex; gap: 8px; align-items: flex-end; max-width: 80%; }
  .dmsg.mine { align-self: flex-end; flex-direction: row-reverse; }
  .dmsg .bubble { background: #fff; padding: 9px 14px; border-radius: 16px; line-height: 1.4; word-break: break-word; box-shadow: 0 1px 1px rgba(0,0,0,0.05); white-space: pre-wrap; }
  .dmsg.mine .bubble { background: var(--accent); color: #fff; }
  .dmsg .time { font-size: 11px; color: var(--muted); margin-top: 2px; }
  .dmsg.mine .time { text-align: right; }
  .dmsg .time .seen { color: var(--accent); margin-left: 4px; }
  .dmsg .time .seen i { margin-right: 3px; }
  .dmsg .via { font-size: 11px; color: var(--muted); margin-top: 2px; font-style: italic; }
  .dmsg.mine .via { color: var(--muted); }

  .day-sep { align-self: center; color: var(--muted); font-size: 11px; text-transform: uppercase; letter-spacing: 0.4px; margin: 8px 0; }

  .thread-reply { background: #fff; border-top: 1px solid #f0f2f5; border-radius: 0 0 12px 12px; padding: 12px 14px; display: flex; gap: 8px; }
  .thread-reply textarea { flex: 1; border: 1px solid var(--border); border-radius: 18px; padding: 10px 14px; font-family: inherit; font-size: 14px; min-height: 40px; max-height: 140px; resize: none; }
  .thread-reply button { background: var(--accent); color: #fff; border: none; border-radius: 50%; width: 40px; height: 40px; cursor: pointer; font-size: 15px; flex-shrink: 0; }
  .thread-reply button:hover { background: var(--accent-hover); }

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
      <div class="empty">Ingen beskeder endnu — skriv den første.</div>
    @else
      @php $lastDay = null; @endphp
      @foreach ($messages as $m)
        @php $day = $m->created_at->format('Y-m-d'); @endphp
        @if ($day !== $lastDay)
          <div class="day-sep">{{ $m->created_at->isToday() ? 'I dag' : ($m->created_at->isYesterday() ? 'I går' : $m->created_at->translatedFormat('j. F Y')) }}</div>
          @php $lastDay = $day; @endphp
        @endif
        <div class="dmsg {{ $m->sender_id === auth()->id() ? 'mine' : '' }}">
          <div>
            <div class="bubble">{{ $m->body }}</div>
            <div class="time">
              {{ $m->created_at->format('H:i') }}
              @if ($m->viaCourse)
                · <i class="fa-solid fa-bullhorn" title="Sendt via Hold-besked"></i> {{ $m->viaCourse->title }}
              @endif
              @if ($m->sender_id === auth()->id() && $m->read_at)
                <span class="seen" title="Set {{ $m->read_at->translatedFormat('j. F H:i') }}">
                  <i class="fa-solid fa-check-double"></i>Set {{ $m->read_at->isToday() ? $m->read_at->format('H:i') : $m->read_at->translatedFormat('j. M H:i') }}
                </span>
              @endif
            </div>
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
