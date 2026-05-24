@push('styles')
<style>
  .chat-shell { }
  .chat-card { background: #fff; border-radius: 12px; box-shadow: 0 1px 2px rgba(0,0,0,0.08); display: flex; flex-direction: column; height: calc(100vh - 180px); height: calc(100dvh - 180px); min-height: 420px; overflow: hidden; }
  .chat-card .head { padding: 14px 18px; border-bottom: 1px solid #f0f2f5; display: flex; gap: 12px; align-items: center; flex-shrink: 0; }
  .chat-card .head h2 { font-size: 16px; font-weight: 700; }
  .chat-card .head .sub { color: var(--muted); font-size: 12px; }
  .chat-stream { flex: 1; overflow-y: auto; padding: 14px 18px; display: flex; flex-direction: column; gap: 10px; background: #fafbfc; }
  .msg { display: flex; gap: 8px; align-items: flex-start; max-width: 80%; }
  .msg.mine { align-self: flex-end; flex-direction: row-reverse; }
  .msg .bubble { background: var(--bubble); padding: 9px 14px; border-radius: 16px; line-height: 1.4; word-break: break-word; }
  .msg .bubble .name { font-weight: 600; font-size: 12px; margin-bottom: 2px; color: var(--muted); }
  .msg.mine .bubble { background: var(--accent); color: #fff; }
  .msg.mine .bubble .name { color: rgba(255,255,255,0.85); }
  .msg .time { font-size: 11px; color: var(--muted); margin-top: 2px; }
  .msg .role-badge { font-size: 10px; font-weight: 700; padding: 1px 6px; border-radius: 8px; margin-left: 4px; vertical-align: middle; background: var(--accent-soft); color: var(--accent); }
  .msg.mine .role-badge { background: rgba(255,255,255,0.25); color: #fff; }
  .chat-composer { padding: 12px 14px; border-top: 1px solid #f0f2f5; display: flex; gap: 8px; flex-shrink: 0; background: #fff; }
  .chat-composer input { flex: 1; border: 1px solid var(--border); border-radius: 22px; padding: 10px 16px; font-size: 14px; }
  .chat-composer button { background: var(--accent); color: #fff; border: none; border-radius: 22px; padding: 0 18px; cursor: pointer; font-weight: 600; }
  .chat-composer button:hover { background: var(--accent-hover); }
  .chat-empty { text-align: center; color: var(--muted); padding: 40px 20px; }
  @media (max-width: 767px) {
    .chat-card {
      height: calc(100vh - 56px);
      height: calc(100dvh - 56px);
      min-height: 0;
      border-radius: 0;
      box-shadow: none;
      margin: -14px -14px 0;
    }
    .chat-card .head { padding: 12px 14px; }
    .chat-stream { padding: 12px 14px; }
    .chat-composer { padding: 10px 12px; padding-bottom: max(10px, env(safe-area-inset-bottom)); }
  }
</style>
@endpush

<div class="chat-shell">
  <div class="chat-card" data-list-url="{{ $listUrl }}" data-send-url="{{ $sendUrl }}">
    @if ($showHead ?? true)
      <div class="head">
        <div style="width:42px;height:42px;background:var(--accent-soft);color:var(--accent);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;">
          <i class="{{ $icon ?? 'fa-solid fa-hashtag' }}"></i>
        </div>
        <div>
          <h2>{{ $title }}</h2>
          <div class="sub">{{ $sub }}</div>
        </div>
      </div>
    @endif
    <div class="chat-stream" id="chatStream">
      <div class="chat-empty">Indlæser…</div>
    </div>
    <form class="chat-composer" id="chatComposer" autocomplete="off">
      @csrf
      <input type="text" name="body" placeholder="Skriv en besked…" maxlength="2000" required autofocus>
      <button type="submit" aria-label="Send"><i class="fa-solid fa-paper-plane"></i></button>
    </form>
  </div>
</div>

@push('scripts')
<script>
(function () {
  var CSRF = document.querySelector('meta[name=csrf-token]').content;
  var card = document.querySelector('.chat-card');
  var listUrl = card.dataset.listUrl;
  var sendUrl = card.dataset.sendUrl;
  var stream = document.getElementById('chatStream');
  var composer = document.getElementById('chatComposer');
  var input = composer.querySelector('input[name="body"]');
  var seenIds = new Set();
  var atBottom = true;

  function escapeHtml(s) { return String(s ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }
  function badgeFor(role) {
    if (role === 'trainer') return '<span class="role-badge">Træner</span>';
    return '';
  }
  function avatar(u) {
    return u.picture_url
      ? '<div class="av sm"><img src="' + escapeHtml(u.picture_url) + '"></div>'
      : '<div class="av sm">' + escapeHtml(u.initials) + '</div>';
  }
  function render(m) {
    if (seenIds.has(m.id)) return null;
    seenIds.add(m.id);
    var el = document.createElement('div');
    el.className = 'msg' + (m.mine ? ' mine' : '');
    el.innerHTML = avatar(m.user) + '<div>' +
      '<div class="bubble"><div class="name">' + escapeHtml(m.user.name) + badgeFor(m.user.role) + '</div>' + escapeHtml(m.body) + '</div>' +
      '<div class="time">' + escapeHtml(m.time_human) + '</div></div>';
    return el;
  }
  function appendAll(messages) {
    var frag = document.createDocumentFragment();
    var added = 0;
    messages.forEach(function (m) { var el = render(m); if (el) { frag.appendChild(el); added++; } });
    if (added) stream.appendChild(frag);
  }
  function scrollToBottomIfNeeded(force) { if (force || atBottom) stream.scrollTop = stream.scrollHeight; }
  stream.addEventListener('scroll', function () { atBottom = stream.scrollHeight - stream.scrollTop - stream.clientHeight < 60; });

  async function loadInitial() {
    try {
      var res = await fetch(listUrl, { headers: { Accept: 'application/json' }});
      var data = await res.json();
      stream.innerHTML = '';
      if (!data.messages.length) {
        stream.innerHTML = '<div class="chat-empty">Ingen beskeder endnu — vær den første til at sige hej 👋</div>';
        return;
      }
      appendAll(data.messages);
      scrollToBottomIfNeeded(true);
    } catch (e) { stream.innerHTML = '<div class="chat-empty" style="color:#b91c1c;">Kunne ikke hente chatten.</div>'; }
  }
  async function poll() {
    try {
      var res = await fetch(listUrl, { headers: { Accept: 'application/json' }});
      var data = await res.json();
      var empty = stream.querySelector('.chat-empty');
      if (data.messages.length && empty) empty.remove();
      appendAll(data.messages);
      scrollToBottomIfNeeded(false);
    } catch {}
  }
  composer.addEventListener('submit', async function (e) {
    e.preventDefault();
    var body = input.value.trim();
    if (!body) return;
    input.value = '';
    try {
      var res = await fetch(sendUrl, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': CSRF, Accept: 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify({ body: body }),
      });
      if (!res.ok) throw new Error('Send failed');
      var data = await res.json();
      var empty = stream.querySelector('.chat-empty');
      if (empty) empty.remove();
      var el = render(data.message);
      if (el) { stream.appendChild(el); scrollToBottomIfNeeded(true); }
    } catch (e) {
      alert('Kunne ikke sende. Prøv igen.');
      input.value = body;
    }
  });

  loadInitial();
  setInterval(poll, 4000);
})();
</script>
@endpush
