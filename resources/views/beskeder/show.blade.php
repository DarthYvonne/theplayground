@extends('layouts.app')
@section('content')

@push('styles')
<style>
  .besk-thread { max-width: 720px; }

  .thread-head { display: flex; gap: 12px; align-items: center; padding: 12px 16px; background: #fff; border-radius: 12px 12px 0 0; box-shadow: 0 1px 2px rgba(0,0,0,0.06); }
  .thread-head .back { color: var(--muted); font-size: 18px; padding: 4px 8px; border-radius: 6px; }
  .thread-head .back:hover { background: var(--hover); color: var(--text); }
  .thread-head .name { font-weight: 700; font-size: 15px; }
  .thread-head .role { color: var(--muted); font-size: 12px; }

  .thread-stream { background: #fafbfc; padding: 16px; display: flex; flex-direction: column; gap: 8px; min-height: 300px; }
  .thread-stream .empty { text-align: center; color: var(--muted); padding: 40px 20px; }

  .dmsg { display: flex; gap: 8px; align-items: flex-end; max-width: 80%; }
  .dmsg.mine { align-self: flex-end; flex-direction: row-reverse; }
  .dmsg .bubble { background: #fff; padding: 9px 14px; border-radius: 16px; line-height: 1.4; word-break: break-word; box-shadow: 0 1px 1px rgba(0,0,0,0.05); white-space: pre-wrap; }
  .dmsg.mine .bubble { background: var(--accent); color: #fff; }
  .dmsg .time { font-size: 11px; color: var(--muted); margin-top: 2px; }
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
    .besk-thread { margin: -14px -14px 0; max-width: none; }
    .thread-head { border-radius: 0; }
    .thread-stream { min-height: calc(100dvh - 56px - 70px - 70px); }
    .thread-reply { border-radius: 0; padding-bottom: max(12px, env(safe-area-inset-bottom)); }
  }
</style>
@endpush

<div class="view-header">
  <h1>
    <a href="{{ route('beskeder.index') }}" style="color:inherit;"><i class="fa-solid fa-arrow-left" style="font-size:16px;margin-right:8px;"></i></a>
    {{ $other->name }}
  </h1>
  @include('partials.header-actions')
</div>

<div class="besk-thread">
  <div class="thread-head">
    <a href="{{ route('beskeder.index') }}" class="back" title="Tilbage"><i class="fa-solid fa-arrow-left"></i></a>
    <a href="{{ route('members.show', $other) }}" style="display:flex;gap:10px;align-items:center;color:inherit;">
      @include('partials.avatar', ['u' => $other, 'size' => 'sm'])
      <div>
        <div class="name">{{ $other->name }}</div>
        <div class="role">@switch($other->role)@case('owner')Ejer@break @case('trainer')Træner@break @case('assistant')Assistent@break @default Medlem @endswitch</div>
      </div>
    </a>
  </div>

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
    <input type="hidden" name="recipient_type" value="user">
    <input type="hidden" name="recipient_id" value="{{ $other->id }}">
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
})();
</script>
@endpush

@endsection
