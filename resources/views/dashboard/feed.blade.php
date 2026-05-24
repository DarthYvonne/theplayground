@extends('layouts.app')
@section('content')

@push('styles')
<style>
  .feed-shell { }

  /* Composer */
  .composer { margin-bottom: 18px; }
  .composer textarea {
    width: 100%; resize: none; border: 1px solid var(--border); background: #fff; border-radius: 14px;
    padding: 14px 18px; font-family: inherit; font-size: 16px; line-height: 1.45; min-height: 56px; max-height: 280px;
    box-shadow: 0 1px 2px rgba(0,0,0,0.04);
  }
  .composer textarea:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px rgba(24,119,242,0.15); }
  .composer-actions { display: flex; justify-content: flex-end; margin-top: 10px; gap: 8px; }
  .composer-actions .btn { padding: 9px 22px; }
  .composer-error { color: var(--danger); font-size: 12px; margin-top: 6px; }

  /* Feed items */
  .feed-list { display: flex; flex-direction: column; gap: 14px; }
  .feed-item { position: relative; background: #fff; border-radius: 12px; box-shadow: 0 1px 2px rgba(0,0,0,0.08); padding: 14px 16px; }
  .feed-head { display: flex; gap: 10px; align-items: center; padding-right: 110px; }
  .feed-head .name { font-weight: 700; }
  .feed-head .meta { color: var(--muted); font-size: 12px; margin-top: 1px; }
  .feed-body { margin-top: 10px; line-height: 1.5; white-space: pre-wrap; word-break: break-word; }
  .feed-context { position: absolute; top: 14px; right: 14px; }
  .feed-type-chip { display: inline-flex; align-items: center; gap: 5px; font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 999px; text-transform: uppercase; letter-spacing: 0.3px; text-decoration: none; max-width: 180px; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }
  a.feed-type-chip:hover { filter: brightness(0.96); }
  .feed-type-chip.platform { background: #e0e7ff; color: #3730a3; }
  .feed-type-chip.course { background: #dcfce7; color: #166534; }
  .feed-type-chip.enroll { background: #fef3c7; color: #92400e; }
  @media (max-width: 540px) {
    .feed-head { padding-right: 0; }
    .feed-context { position: static; margin-bottom: 8px; display: block; }
    .feed-type-chip { max-width: 100%; }
  }
  .feed-enroll-line { margin-top: 10px; padding: 10px 12px; background: #fafbfc; border-radius: 8px; display: flex; gap: 10px; align-items: center; }
  .feed-enroll-line img, .feed-enroll-line .ph { width: 36px; height: 36px; border-radius: 8px; object-fit: cover; flex-shrink: 0; }
  .feed-enroll-line .ph { background: var(--accent-soft); color: var(--accent); display: flex; align-items: center; justify-content: center; font-size: 14px; }
  .feed-enroll-line .ct { flex: 1; font-size: 13px; }
  .feed-enroll-line .ct .t { font-weight: 700; }

  .feed-footer { margin-top: 12px; padding-top: 10px; border-top: 1px solid #f0f2f5; display: flex; align-items: center; justify-content: space-between; gap: 10px; }
  .respekt-count { color: var(--text); font-size: 13px; font-weight: 700; display: inline-flex; align-items: center; gap: 6px; min-height: 1em; background: none; border: none; padding: 0; font-family: inherit; cursor: default; }
  .respekt-count:not(:empty) { cursor: pointer; }
  .respekt-count:not(:empty):hover { color: var(--accent); }
  .respekt-count i { color: var(--accent); font-size: 13px; }

  /* Respekt modal */
  .resp-backdrop { position: fixed; inset: 0; max-width: none; margin: 0; background: rgba(0,0,0,0.45); z-index: 9998; display: none; align-items: center; justify-content: center; padding: 20px; }
  .resp-backdrop.open { display: flex; }
  .resp-modal { background: #fff; border-radius: 12px; width: 100%; max-width: 360px; max-height: 70vh; display: flex; flex-direction: column; box-shadow: 0 10px 32px rgba(0,0,0,0.22); overflow: hidden; }
  .resp-head { padding: 14px 18px; border-bottom: 1px solid #f0f2f5; display: flex; align-items: center; gap: 10px; }
  .resp-head .title { font-weight: 700; flex: 1; }
  .resp-head .title i { color: var(--accent); margin-right: 6px; }
  .resp-close { background: none; border: none; cursor: pointer; padding: 6px 10px; border-radius: 6px; color: var(--muted); font-size: 16px; }
  .resp-close:hover { background: var(--hover); color: var(--text); }
  .resp-body { overflow-y: auto; padding: 6px; }
  .resp-row { display: flex; gap: 10px; align-items: center; padding: 8px 10px; border-radius: 8px; color: inherit; }
  .resp-row:hover { background: var(--hover); }
  .resp-row .nm { font-weight: 600; }
  .resp-loading, .resp-error { color: var(--muted); padding: 18px; text-align: center; font-size: 13px; }
  .respekt-btn { background: none; border: none; padding: 0; cursor: pointer; font-family: inherit; font-size: 14px; font-weight: 400; color: var(--accent); display: inline-flex; align-items: center; gap: 6px; }
  .respekt-btn .respekt-text { text-decoration: underline; }
  .respekt-btn:hover .respekt-text { text-decoration-thickness: 2px; }
  .respekt-btn.active { color: var(--accent); }
  .respekt-btn i { font-size: 14px; text-decoration: none; }

  .feed-empty { background: #fff; border-radius: 12px; padding: 40px 20px; text-align: center; color: var(--muted); }
  .feed-loading { color: var(--muted); text-align: center; padding: 20px; font-size: 13px; }
</style>
@endpush

<div class="view-header">
  <h1>Feed</h1>
  @include('partials.header-actions')
</div>

<div class="feed-shell">
  <form id="feedComposer" class="composer" autocomplete="off">
    @csrf
    <textarea id="feedComposerInput" name="body" placeholder="Hvad sker der, {{ explode(' ', trim($user->name))[0] }}?" maxlength="2000" rows="1"></textarea>
    <div class="composer-actions">
      <button type="submit" class="btn btn-primary" id="feedComposerSubmit" disabled>Slå op</button>
    </div>
    <div id="feedComposerError" class="composer-error" style="display:none;"></div>
  </form>

  <div class="feed-loading" id="feedLoading">Indlæser feed…</div>
  <div class="feed-list" id="feedList"></div>
  <div class="feed-empty" id="feedEmpty" style="display:none;">
    <h3 style="color:var(--text);margin-bottom:6px;">Stille her endnu</h3>
    <p>Skriv det første opslag herover.</p>
  </div>
</div>

<div class="resp-backdrop" id="respBackdrop" role="dialog" aria-modal="true" aria-labelledby="respTitle">
  <div class="resp-modal">
    <div class="resp-head">
      <div class="title" id="respTitle"><i class="fa-solid fa-hand-fist"></i> Respekt</div>
      <button type="button" class="resp-close" id="respClose" aria-label="Luk"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="resp-body" id="respBody">
      <div class="resp-loading">Indlæser…</div>
    </div>
  </div>
</div>

@push('scripts')
<script>
(function () {
  var CSRF = document.querySelector('meta[name=csrf-token]').content;
  var composer = document.getElementById('feedComposer');
  var input = document.getElementById('feedComposerInput');
  var submit = document.getElementById('feedComposerSubmit');
  var errBox = document.getElementById('feedComposerError');
  var list = document.getElementById('feedList');
  var loading = document.getElementById('feedLoading');
  var empty = document.getElementById('feedEmpty');
  var seen = new Set();

  var FEED_URL = '{{ url('/api/feed') }}';
  var SEND_URL = '{{ url('/api/chat/platform') }}';

  function escapeHtml(s) { return String(s ?? '').replace(/[&<>"']/g, function (m) { return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[m]; }); }
  function avatar(u) {
    var inner = u.picture_url
      ? '<img src="' + escapeHtml(u.picture_url) + '" alt="">'
      : escapeHtml(u.initials);
    return '<div class="av">' + inner + '</div>';
  }
  function contextChip(it) {
    if (it.type === 'platform_message') {
      return '<span class="feed-type-chip platform"><i class="fa-solid fa-hashtag"></i> Fælles</span>';
    }
    if (it.type === 'course_message' && it.course) {
      return '<a class="feed-type-chip course" href="' + escapeHtml(it.course.chat_url) + '" title="' + escapeHtml(it.course.title) + '">' +
        '<i class="fa-regular fa-comments"></i> ' + escapeHtml(it.course.title) + '</a>';
    }
    if (it.type === 'enrollment' && it.course) {
      return '<a class="feed-type-chip enroll" href="' + escapeHtml(it.course.url) + '" title="' + escapeHtml(it.course.title) + '">' +
        '<i class="fa-solid fa-user-plus"></i> ' + escapeHtml(it.course.title) + '</a>';
    }
    return '';
  }
  function renderItem(it) {
    var el = document.createElement('article');
    el.className = 'feed-item';
    el.dataset.id = it.id;

    var head =
      '<div class="feed-context">' + contextChip(it) + '</div>' +
      '<div class="feed-head">' +
        avatar(it.user) +
        '<div style="flex:1;min-width:0;">' +
          '<div class="name">' + escapeHtml(it.user.name) + '</div>' +
          '<div class="meta">' + escapeHtml(it.time_human) + '</div>' +
        '</div>' +
      '</div>';

    var body = '';
    if (it.type === 'enrollment' && it.course) {
      body =
        '<div class="feed-body">' + escapeHtml(it.user.name) + ' tilmeldte sig <strong>' + escapeHtml(it.course.title) + '</strong>.</div>' +
        '<a href="' + escapeHtml(it.course.url) + '" style="text-decoration:none;color:inherit;">' +
          '<div class="feed-enroll-line">' +
            (it.course.image_url
              ? '<img src="' + escapeHtml(it.course.image_url) + '" alt="">'
              : '<div class="ph"><i class="fa-solid fa-dumbbell"></i></div>') +
            '<div class="ct"><div class="t">' + escapeHtml(it.course.title) + '</div><div style="color:var(--muted);">Se hold →</div></div>' +
          '</div>' +
        '</a>';
    } else if (it.body) {
      body = '<div class="feed-body">' + escapeHtml(it.body) + '</div>';
    }

    var footer =
      '<div class="feed-footer">' +
        '<button type="button" class="respekt-count"' +
          ' data-target-type="' + escapeHtml(it.target_type) + '"' +
          ' data-target-id="' + escapeHtml(String(it.target_id)) + '">' +
          (it.respekt_count > 0 ? '<i class="fa-solid fa-hand-fist"></i>' + it.respekt_count : '') +
        '</button>' +
        '<button type="button" class="respekt-btn ' + (it.you_respekted ? 'active' : '') + '"' +
          ' data-target-type="' + escapeHtml(it.target_type) + '"' +
          ' data-target-id="' + escapeHtml(String(it.target_id)) + '">' +
          '<i class="fa-solid fa-hand-fist"></i> <span class="respekt-text">Respekt</span>' +
        '</button>' +
      '</div>';

    el.innerHTML = head + body + footer;
    return el;
  }

  function respektCountText(n) { return n > 0 ? '<i class="fa-solid fa-hand-fist"></i>' + n : ''; }

  var respBackdrop = document.getElementById('respBackdrop');
  var respBody = document.getElementById('respBody');
  var respClose = document.getElementById('respClose');
  function closeRespModal() { respBackdrop.classList.remove('open'); }
  respClose.addEventListener('click', closeRespModal);
  respBackdrop.addEventListener('click', function (e) { if (e.target === respBackdrop) closeRespModal(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeRespModal(); });

  async function openRespModal(type, id) {
    respBody.innerHTML = '<div class="resp-loading">Indlæser…</div>';
    respBackdrop.classList.add('open');
    try {
      var url = '{{ url('/api/respekt') }}?target_type=' + encodeURIComponent(type) + '&target_id=' + encodeURIComponent(id);
      var res = await fetch(url, { headers: { Accept: 'application/json' }});
      if (!res.ok) throw new Error('list failed');
      var data = await res.json();
      if (!data.users.length) {
        respBody.innerHTML = '<div class="resp-loading">Ingen Respekt endnu.</div>';
        return;
      }
      respBody.innerHTML = data.users.map(function (u) {
        var av = u.picture_url
          ? '<div class="av sm"><img src="' + escapeHtml(u.picture_url) + '" alt=""></div>'
          : '<div class="av sm">' + escapeHtml(u.initials) + '</div>';
        return '<a class="resp-row" href="' + escapeHtml(u.profile_url) + '">' + av + '<span class="nm">' + escapeHtml(u.name) + '</span></a>';
      }).join('');
    } catch (err) {
      respBody.innerHTML = '<div class="resp-error">Kunne ikke hente listen.</div>';
    }
  }

  list.addEventListener('click', async function (e) {
    var countBtn = e.target.closest('.respekt-count');
    if (countBtn) {
      if (!countBtn.textContent.trim()) return; // empty count — nothing to show
      e.preventDefault();
      openRespModal(countBtn.dataset.targetType, countBtn.dataset.targetId);
      return;
    }
    var btn = e.target.closest('.respekt-btn');
    if (!btn) return;
    e.preventDefault();
    var type = btn.dataset.targetType;
    var id = btn.dataset.targetId;
    btn.disabled = true;
    try {
      var res = await fetch('{{ url('/api/respekt') }}', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': CSRF, Accept: 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify({ target_type: type, target_id: id }),
      });
      if (!res.ok) throw new Error('Respekt failed');
      var data = await res.json();
      btn.classList.toggle('active', !!data.respekted);
      var counter = btn.parentElement.querySelector('.respekt-count');
      if (counter) counter.innerHTML = respektCountText(data.count);
    } catch (err) {
      // silent — user can retry
    } finally {
      btn.disabled = false;
    }
  });

  function paint(items) {
    var frag = document.createDocumentFragment();
    items.forEach(function (it) {
      if (seen.has(it.id)) return;
      seen.add(it.id);
      frag.appendChild(renderItem(it));
    });
    if (frag.childNodes.length) {
      // newest first — but since we get sorted desc, prepend in reverse to keep order
      var nodes = Array.prototype.slice.call(frag.childNodes);
      nodes.reverse().forEach(function (n) { list.insertBefore(n, list.firstChild); });
    }
    var hasAny = list.children.length > 0;
    empty.style.display = hasAny ? 'none' : 'block';
  }

  async function load(initial) {
    try {
      var res = await fetch(FEED_URL, { headers: { Accept: 'application/json' }});
      var data = await res.json();
      if (initial) {
        list.innerHTML = '';
        seen.clear();
        // initial load: items come desc — append in order
        data.items.forEach(function (it) {
          if (seen.has(it.id)) return;
          seen.add(it.id);
          list.appendChild(renderItem(it));
        });
        loading.style.display = 'none';
        empty.style.display = list.children.length ? 'none' : 'block';
      } else {
        paint(data.items);
      }
    } catch (e) {
      loading.textContent = 'Kunne ikke hente feedet.';
    }
  }

  function autosize() {
    input.style.height = 'auto';
    input.style.height = Math.min(input.scrollHeight, 240) + 'px';
    submit.disabled = input.value.trim().length === 0;
  }
  input.addEventListener('input', autosize);
  input.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); composer.requestSubmit(); }
  });

  composer.addEventListener('submit', async function (e) {
    e.preventDefault();
    var body = input.value.trim();
    if (!body) return;
    submit.disabled = true;
    errBox.style.display = 'none';
    try {
      var res = await fetch(SEND_URL, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': CSRF, Accept: 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify({ body: body }),
      });
      if (!res.ok) throw new Error('Send failed');
      input.value = '';
      autosize();
      await load(false);
    } catch (err) {
      errBox.textContent = 'Kunne ikke sende. Prøv igen.';
      errBox.style.display = 'block';
      submit.disabled = false;
    }
  });

  load(true);
  setInterval(function () { load(false); }, 8000);
})();
</script>
@endpush

@endsection
