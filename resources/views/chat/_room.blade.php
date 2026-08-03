@push('styles')
<style>
  .chat-shell { }
  .chat-card { position: relative; background: #fff; border-radius: 12px; box-shadow: 0 1px 2px rgba(0,0,0,0.08); display: flex; flex-direction: column; height: calc(100vh - 180px); height: calc(100dvh - 180px); min-height: 420px; overflow: hidden; }
  .chat-card .head { padding: 14px 18px; border-bottom: 1px solid #f0f2f5; display: flex; gap: 12px; align-items: center; flex-shrink: 0; }
  .chat-card .head h2 { font-size: 16px; font-weight: 700; }
  .chat-card .head .sub { color: var(--muted); font-size: 12px; }
  .chat-card .head .ico-badge { width: 42px; height: 42px; background: var(--accent-soft); color: var(--accent); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }

  .chat-stream { flex: 1; overflow-y: auto; overflow-anchor: none; padding: 16px 18px 10px; display: flex; flex-direction: column; background: #f5f7fa; scrollbar-width: thin; scrollbar-color: #cfd4db transparent; }
  .chat-stream::-webkit-scrollbar { width: 8px; }
  .chat-stream::-webkit-scrollbar-thumb { background: #cfd4db; border-radius: 4px; border: 2px solid #f5f7fa; }

  /* Day separator — one per calendar day, so a week of chat reads as a week. */
  .day-sep { display: flex; align-items: center; gap: 10px; margin: 10px 0 14px; }
  .day-sep::before, .day-sep::after { content: ''; flex: 1; height: 1px; background: #e3e7ed; }
  .day-sep span { font-size: 11px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: 0.3px; }
  .day-sep:first-child { margin-top: 0; }

  /* A group is one run of messages from the same person inside GROUP_GAP_MS.
     Avatar, name and clock appear once per group, not once per message. */
  .msg-group { display: flex; gap: 8px; align-items: flex-start; margin-bottom: 12px; max-width: min(78%, 560px); }
  .msg-group.mine { align-self: flex-end; }
  .msg-group .stack { display: flex; flex-direction: column; align-items: flex-start; min-width: 0; }
  .msg-group.mine .stack { align-items: flex-end; }
  .msg-group .who { font-size: 12px; font-weight: 600; color: #4b4f56; margin: 0 0 4px 12px; display: flex; align-items: center; gap: 5px; }

  .bubble { background: #fff; border: 1px solid #e6e9ee; box-shadow: 0 1px 1px rgba(0,0,0,0.03); padding: 8px 13px; border-radius: 18px; line-height: 1.45; white-space: pre-wrap; overflow-wrap: anywhere; max-width: 100%; }
  .bubble + .bubble { margin-top: 2px; }
  .bubble a { color: var(--accent); text-decoration: underline; }
  .msg-group.mine .bubble { background: var(--accent); border-color: var(--accent); color: #fff; box-shadow: 0 1px 2px rgba(24,119,242,0.25); }
  .msg-group.mine .bubble a { color: #fff; }
  /* Flatten the inner corners so a run of bubbles reads as one block.
     Bubbles are the only <p> in .stack, so :first/:last-of-type is safe here. */
  .msg-group:not(.mine) .bubble:not(:first-of-type) { border-top-left-radius: 6px; }
  .msg-group:not(.mine) .bubble:not(:last-of-type) { border-bottom-left-radius: 6px; }
  .msg-group.mine .bubble:not(:first-of-type) { border-top-right-radius: 6px; }
  .msg-group.mine .bubble:not(:last-of-type) { border-bottom-right-radius: 6px; }

  .bubble.in { animation: bubbleIn 0.18s ease-out; }
  @keyframes bubbleIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: none; } }
  .bubble.pending { opacity: 0.55; }
  .bubble.failed { background: #fee2e2; border-color: #fca5a5; color: #7f1d1d; box-shadow: none; }

  .send-error { font-size: 11px; color: #b91c1c; margin-top: 3px; display: flex; align-items: center; gap: 5px; }
  .send-error button { background: none; border: none; padding: 0; font: inherit; font-weight: 600; color: #b91c1c; text-decoration: underline; cursor: pointer; }

  .stamp { font-size: 11px; color: var(--muted); margin: 3px 12px 0; }
  .role-badge { font-size: 10px; font-weight: 700; padding: 1px 6px; border-radius: 8px; background: var(--accent-soft); color: var(--accent); }

  .chat-empty { margin: auto; text-align: center; color: var(--muted); padding: 40px 20px; }
  .chat-empty i { font-size: 30px; color: #c8cfd8; display: block; margin-bottom: 10px; }

  /* Loading placeholder — shaped like the conversation it replaces. */
  .chat-skeleton { padding-top: 4px; }
  .sk-row { display: flex; gap: 8px; align-items: flex-start; margin-bottom: 14px; }
  .sk-row.right { justify-content: flex-end; }
  .sk-av, .sk-b { background: linear-gradient(90deg, #e9edf2 25%, #f2f5f8 37%, #e9edf2 63%); background-size: 400% 100%; animation: skShimmer 1.4s ease infinite; }
  .sk-av { width: 32px; height: 32px; border-radius: 50%; flex-shrink: 0; }
  .sk-b { height: 34px; border-radius: 18px; }
  @keyframes skShimmer { from { background-position: 100% 50%; } to { background-position: 0 50%; } }

  /* "New messages" pill — only while the reader is scrolled away from the end. */
  .chat-jump { position: absolute; left: 50%; transform: translate(-50%, 8px); bottom: 76px; background: var(--accent); color: #fff; border: none; border-radius: 999px; padding: 7px 14px; font: inherit; font-size: 13px; font-weight: 600; cursor: pointer; box-shadow: 0 4px 14px rgba(0,0,0,0.18); display: none; align-items: center; gap: 7px; z-index: 2; }
  .chat-jump.show { display: inline-flex; animation: jumpIn 0.18s ease-out forwards; }
  @keyframes jumpIn { to { transform: translate(-50%, 0); } }

  .chat-composer { padding: 12px 14px; border-top: 1px solid #f0f2f5; display: flex; gap: 8px; align-items: flex-end; flex-shrink: 0; background: #fff; }
  .chat-composer textarea { flex: 1; border: 1px solid transparent; background: #f0f2f5; border-radius: 20px; padding: 10px 16px; font-family: inherit; font-size: 15px; line-height: 1.4; min-height: 40px; max-height: 140px; resize: none; transition: background 0.12s, border-color 0.12s; }
  .chat-composer textarea:focus { background: #fff; border-color: var(--accent); box-shadow: 0 0 0 3px rgba(24,119,242,0.15); }
  .chat-composer button { background: var(--accent); color: #fff; border: none; border-radius: 50%; width: 40px; height: 40px; padding: 0; cursor: pointer; font-size: 15px; flex-shrink: 0; display: inline-flex; align-items: center; justify-content: center; transition: background 0.12s, transform 0.12s; }
  .chat-composer button:hover:not(:disabled) { background: var(--accent-hover); }
  .chat-composer button:active:not(:disabled) { transform: scale(0.92); }
  .chat-composer button:disabled { background: #e4e6eb; color: #bcc0c4; cursor: default; }

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
    .chat-stream { padding: 12px 14px 8px; }
    .msg-group { max-width: 86%; }
    .chat-composer { padding: 10px 12px; padding-bottom: max(10px, env(safe-area-inset-bottom)); }
    /* iOS zooms inputs under 16px on focus. */
    .chat-composer textarea { font-size: 16px; }
  }
</style>
@endpush

<div class="chat-shell">
  <div class="chat-card" data-list-url="{{ $listUrl }}" data-send-url="{{ $sendUrl }}" data-me="{{ auth()->id() }}">
    @if ($showHead ?? true)
      <div class="head">
        <div class="ico-badge"><i class="{{ $icon ?? 'fa-solid fa-hashtag' }}"></i></div>
        <div>
          <h2>{{ $title }}</h2>
          <div class="sub">{{ $sub }}</div>
        </div>
      </div>
    @endif
    <div class="chat-stream" id="chatStream" role="log" aria-live="polite" aria-label="Beskeder">
      <div class="chat-skeleton" aria-hidden="true">
        <div class="sk-row"><span class="sk-av"></span><span class="sk-b" style="width:46%"></span></div>
        <div class="sk-row right"><span class="sk-b" style="width:38%"></span></div>
        <div class="sk-row"><span class="sk-av"></span><span class="sk-b" style="width:60%"></span></div>
      </div>
    </div>
    <button type="button" class="chat-jump" id="chatJump">
      <i class="fa-solid fa-arrow-down"></i> Nye beskeder <span id="chatJumpCount"></span>
    </button>
    {{-- No @csrf and a <textarea> (not <input>) — Chrome's password/payment
         autofill heuristic only fires on inputs in form-with-csrf. The fetch
         below carries the X-CSRF-TOKEN header, so the hidden field isn't needed. --}}
    <form class="chat-composer" id="chatComposer" autocomplete="off">
      <textarea name="body" id="chatComposerBody" placeholder="Skriv en besked…" maxlength="2000" rows="1" required autofocus
                autocomplete="off" autocorrect="off" autocapitalize="sentences" spellcheck="true"></textarea>
      <button type="submit" aria-label="Send" disabled><i class="fa-solid fa-paper-plane"></i></button>
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
  var ME = parseInt(card.dataset.me, 10);
  var stream = document.getElementById('chatStream');
  var composer = document.getElementById('chatComposer');
  var input = document.getElementById('chatComposerBody');
  var sendBtn = composer.querySelector('button[type=submit]');
  var jump = document.getElementById('chatJump');
  var jumpCount = document.getElementById('chatJumpCount');

  // Two messages from the same person land in one group if they're this close.
  var GROUP_GAP_MS = 5 * 60 * 1000;

  var seen = new Set();
  var pending = [];          // {el, body} for messages not yet confirmed by the server
  var atBottom = true;
  var unseen = 0;
  var group = null, groupUser = null, groupMine = false, groupTs = 0, dayKey = null;

  /* ---- formatting ---- */
  var DAYS = ['Søndag','Mandag','Tirsdag','Onsdag','Torsdag','Fredag','Lørdag'];
  var MONTHS = ['januar','februar','marts','april','maj','juni','juli','august','september','oktober','november','december'];
  function dateKey(d) { return d.getFullYear() + '-' + d.getMonth() + '-' + d.getDate(); }
  function pad(n) { return (n < 10 ? '0' : '') + n; }
  function clock(d) { return pad(d.getHours()) + '.' + pad(d.getMinutes()); }
  function dayLabel(d) {
    var now = new Date();
    var days = Math.round((new Date(now.getFullYear(), now.getMonth(), now.getDate()) - new Date(d.getFullYear(), d.getMonth(), d.getDate())) / 86400000);
    if (days === 0) return 'I dag';
    if (days === 1) return 'I går';
    if (days > 1 && days < 7) return DAYS[d.getDay()];
    return d.getDate() + '. ' + MONTHS[d.getMonth()] + (d.getFullYear() !== now.getFullYear() ? ' ' + d.getFullYear() : '');
  }
  function fullStamp(d) { return d.getDate() + '. ' + MONTHS[d.getMonth()] + ' ' + d.getFullYear() + ' kl. ' + clock(d); }

  function escapeHtml(s) { return String(s ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }
  // Runs on already-escaped text, so nothing here can inject markup.
  var URL_RE = /\b((?:https?:\/\/|www\.)[^\s<]+[^\s<.,:;!?"')\]}])/gi;
  function linkify(escaped) {
    return escaped.replace(URL_RE, function (u) {
      return '<a href="' + (/^www\./i.test(u) ? 'https://' + u : u) + '" target="_blank" rel="noopener noreferrer nofollow">' + u + '</a>';
    });
  }
  function badgeFor(role) { return role === 'trainer' ? '<span class="role-badge">Træner</span>' : ''; }
  function avatarHtml(u) {
    return u.picture_url
      ? '<div class="av sm"><img src="' + escapeHtml(u.picture_url) + '" alt=""></div>'
      : '<div class="av sm">' + escapeHtml(u.initials || '') + '</div>';
  }

  /* ---- rendering ---- */
  function resetStream() {
    stream.innerHTML = '';
    group = null; groupUser = null; groupTs = 0; dayKey = null;
  }
  function dropEmpty() {
    var e = stream.querySelector('.chat-empty, .chat-skeleton');
    if (e) e.remove();
  }
  function showEmpty(html) {
    resetStream();
    stream.innerHTML = html;
  }

  function addMessage(m, opts) {
    opts = opts || {};
    var d = new Date(m.created_at);
    var ts = d.getTime();
    var key = dateKey(d);

    if (key !== dayKey) {
      var sep = document.createElement('div');
      sep.className = 'day-sep';
      sep.innerHTML = '<span>' + escapeHtml(dayLabel(d)) + '</span>';
      stream.appendChild(sep);
      dayKey = key;
      group = null;
    }

    var mine = !!m.mine;
    if (!group || groupMine !== mine || groupUser !== m.user.id || ts - groupTs > GROUP_GAP_MS) {
      group = document.createElement('div');
      group.className = 'msg-group' + (mine ? ' mine' : '');
      // Your own name and face are never news — only render them for others.
      group.innerHTML = (mine ? '' : avatarHtml(m.user)) + '<div class="stack">' +
        (mine ? '' : '<div class="who">' + escapeHtml(m.user.name) + badgeFor(m.user.role) + '</div>') +
        '<time class="stamp"></time></div>';
      stream.appendChild(group);
      groupUser = m.user.id;
      groupMine = mine;
    }
    groupTs = ts;

    var stack = group.querySelector('.stack');
    var stamp = stack.querySelector('.stamp');
    var b = document.createElement('p');
    b.className = 'bubble' + (opts.pending ? ' pending' : '') + (opts.animate ? ' in' : '');
    if (m.id != null) b.dataset.id = m.id;
    b.innerHTML = linkify(escapeHtml(m.body));
    stack.insertBefore(b, stamp);
    // The clock belongs to the group's newest message.
    stamp.textContent = clock(d);
    stamp.setAttribute('datetime', d.toISOString());
    stamp.title = fullStamp(d);
    return b;
  }

  /* An optimistic bubble and its server copy are the same message. Match on
     body so a poll that beats the send response doesn't render it twice. */
  function adopt(m) {
    if (!m.mine) return false;
    for (var i = 0; i < pending.length; i++) {
      if (pending[i].body !== m.body) continue;
      var el = pending[i].el;
      el.classList.remove('pending', 'failed');
      el.dataset.id = m.id;
      var err = el.nextElementSibling;
      if (err && err.classList.contains('send-error')) err.remove();
      pending.splice(i, 1);
      seen.add(m.id);
      return true;
    }
    return false;
  }

  function appendAll(messages, animate) {
    var added = 0;
    messages.forEach(function (m) {
      if (seen.has(m.id) || adopt(m)) return;
      seen.add(m.id);
      addMessage(m, { animate: animate });
      added++;
    });
    return added;
  }

  /* ---- scrolling ---- */
  function scrollToBottom() { stream.scrollTop = stream.scrollHeight; }
  stream.addEventListener('scroll', function () {
    atBottom = stream.scrollHeight - stream.scrollTop - stream.clientHeight < 60;
    if (atBottom) hideJump();
  });
  function showJump() { jumpCount.textContent = unseen > 1 ? '(' + unseen + ')' : ''; jump.classList.add('show'); }
  function hideJump() { unseen = 0; jump.classList.remove('show'); }
  jump.addEventListener('click', function () { scrollToBottom(); hideJump(); });

  /* ---- composer ---- */
  function autosize() { input.style.height = 'auto'; input.style.height = Math.min(input.scrollHeight, 140) + 'px'; }
  function syncSendBtn() { sendBtn.disabled = input.value.trim() === ''; }
  input.addEventListener('input', function () { autosize(); syncSendBtn(); });
  input.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      composer.requestSubmit();
    }
  });

  async function deliver(body, el) {
    try {
      var res = await fetch(sendUrl, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': CSRF, Accept: 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify({ body: body }),
      });
      if (!res.ok) throw new Error('Send failed');
      var data = await res.json();
      adopt(data.message);
    } catch (e) {
      markFailed(el, body);
    }
  }

  /* Stays in `pending`: if the write actually landed and only the response was
     lost, the next poll adopts this bubble and clears the error by itself. */
  function markFailed(el, body) {
    el.classList.remove('pending');
    el.classList.add('failed');
    if (el.nextElementSibling && el.nextElementSibling.classList.contains('send-error')) return;
    var err = document.createElement('div');
    err.className = 'send-error';
    err.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Ikke sendt · <button type="button">Prøv igen</button>';
    el.insertAdjacentElement('afterend', err);
    err.querySelector('button').addEventListener('click', function () {
      err.remove();
      el.classList.remove('failed');
      el.classList.add('pending');
      deliver(body, el);
    });
  }

  composer.addEventListener('submit', function (e) {
    e.preventDefault();
    var body = input.value.trim();
    if (!body) return;
    input.value = '';
    autosize();
    syncSendBtn();
    dropEmpty();
    // Show it immediately; the network catches up.
    var el = addMessage(
      { id: null, body: body, mine: true, created_at: new Date().toISOString(), user: { id: ME } },
      { pending: true, animate: true }
    );
    pending.push({ el: el, body: body });
    scrollToBottom();
    deliver(body, el);
  });

  /* ---- loading ---- */
  async function loadInitial() {
    try {
      var res = await fetch(listUrl, { headers: { Accept: 'application/json' } });
      var data = await res.json();
      if (!data.messages.length) {
        showEmpty('<div class="chat-empty"><i class="fa-regular fa-comments"></i>Ingen beskeder endnu — vær den første til at sige hej 👋</div>');
        return;
      }
      resetStream();
      appendAll(data.messages, false);
      scrollToBottom();
    } catch (e) {
      showEmpty('<div class="chat-empty" style="color:#b91c1c;"><i class="fa-solid fa-triangle-exclamation" style="color:#fca5a5;"></i>Kunne ikke hente chatten.</div>');
    }
  }

  async function poll() {
    try {
      var res = await fetch(listUrl, { headers: { Accept: 'application/json' } });
      if (!res.ok) return;
      var data = await res.json();
      if (!data.messages.length) return;
      dropEmpty();
      var added = appendAll(data.messages, true);
      if (!added) return;
      if (atBottom) scrollToBottom();
      else { unseen += added; showJump(); }
    } catch (e) {}
  }

  syncSendBtn();
  loadInitial();
  setInterval(poll, 4000);
})();
</script>
@endpush
