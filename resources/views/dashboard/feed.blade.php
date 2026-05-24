@extends('layouts.app')
@section('content')

@push('styles')
<style>
  .feed-shell { max-width: 620px; }

  /* Composer */
  .composer { padding: 14px 16px; }
  .composer-row { display: flex; gap: 12px; align-items: flex-start; }
  .composer textarea {
    flex: 1; resize: none; border: none; background: var(--hover); border-radius: 22px;
    padding: 12px 16px; font-family: inherit; font-size: 15px; line-height: 1.4; min-height: 44px; max-height: 240px;
  }
  .composer textarea:focus { outline: none; box-shadow: 0 0 0 2px rgba(24,119,242,0.15); }
  .composer-actions { display: flex; justify-content: flex-end; margin-top: 10px; gap: 8px; }
  .composer-actions .btn { padding: 8px 18px; }
  .composer-error { color: var(--danger); font-size: 12px; margin-top: 6px; }

  /* Feed items */
  .feed-list { display: flex; flex-direction: column; gap: 14px; }
  .feed-item { background: #fff; border-radius: 12px; box-shadow: 0 1px 2px rgba(0,0,0,0.08); padding: 14px 16px; }
  .feed-head { display: flex; gap: 10px; align-items: center; }
  .feed-head .name { font-weight: 700; }
  .feed-head .meta { color: var(--muted); font-size: 12px; margin-top: 1px; }
  .feed-head .meta a { color: var(--accent); font-weight: 600; }
  .feed-head .role-pill { font-size: 10px; font-weight: 700; padding: 1px 6px; border-radius: 8px; background: var(--accent-soft); color: var(--accent); margin-left: 6px; vertical-align: middle; }
  .feed-body { margin-top: 10px; line-height: 1.5; white-space: pre-wrap; word-break: break-word; }
  .feed-type-chip { font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 999px; text-transform: uppercase; letter-spacing: 0.3px; }
  .feed-type-chip.platform { background: #e0e7ff; color: #3730a3; }
  .feed-type-chip.course { background: #dcfce7; color: #166534; }
  .feed-type-chip.enroll { background: #fef3c7; color: #92400e; }
  .feed-enroll-line { margin-top: 10px; padding: 10px 12px; background: #fafbfc; border-radius: 8px; display: flex; gap: 10px; align-items: center; }
  .feed-enroll-line img, .feed-enroll-line .ph { width: 36px; height: 36px; border-radius: 8px; object-fit: cover; flex-shrink: 0; }
  .feed-enroll-line .ph { background: var(--accent-soft); color: var(--accent); display: flex; align-items: center; justify-content: center; font-size: 14px; }
  .feed-enroll-line .ct { flex: 1; font-size: 13px; }
  .feed-enroll-line .ct .t { font-weight: 700; }

  .feed-empty { background: #fff; border-radius: 12px; padding: 40px 20px; text-align: center; color: var(--muted); }
  .feed-loading { color: var(--muted); text-align: center; padding: 20px; font-size: 13px; }
</style>
@endpush

<div class="view-header">
  <h1>Start</h1>
  @include('partials.header-actions')
</div>

@include('dashboard._subnav')

<div class="feed-shell">
  <div class="card composer">
    <form id="feedComposer" autocomplete="off">
      @csrf
      <div class="composer-row">
        @include('partials.avatar', ['u' => $user, 'size' => 'sm'])
        <textarea id="feedComposerInput" name="body" placeholder="Hvad sker der i klubben, {{ explode(' ', trim($user->name))[0] }}?" maxlength="2000" rows="1"></textarea>
      </div>
      <div class="composer-actions">
        <button type="submit" class="btn btn-primary" id="feedComposerSubmit" disabled>Slå op</button>
      </div>
      <div id="feedComposerError" class="composer-error" style="display:none;"></div>
    </form>
  </div>

  <div class="feed-loading" id="feedLoading">Indlæser feed…</div>
  <div class="feed-list" id="feedList"></div>
  <div class="feed-empty" id="feedEmpty" style="display:none;">
    <h3 style="color:var(--text);margin-bottom:6px;">Stille her endnu</h3>
    <p>Skriv det første opslag herover.</p>
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
  function roleBadge(role) {
    if (role === 'owner') return '<span class="role-pill">Ejer</span>';
    if (role === 'trainer') return '<span class="role-pill">Træner</span>';
    return '';
  }
  function avatar(u) {
    var inner = u.picture_url
      ? '<img src="' + escapeHtml(u.picture_url) + '" alt="">'
      : escapeHtml(u.initials);
    return '<div class="av">' + inner + '</div>';
  }
  function typeChip(type, course) {
    if (type === 'platform_message') return '<span class="feed-type-chip platform"><i class="fa-solid fa-hashtag"></i> Fælles</span>';
    if (type === 'course_message') return '<span class="feed-type-chip course"><i class="fa-regular fa-comments"></i> ' + escapeHtml(course ? course.title : 'Hold') + '</span>';
    if (type === 'enrollment') return '<span class="feed-type-chip enroll"><i class="fa-solid fa-user-plus"></i> Tilmelding</span>';
    return '';
  }
  function courseLink(course) {
    if (!course) return '';
    return '<a href="' + escapeHtml(course.chat_url || course.url) + '">' + escapeHtml(course.title) + '</a>';
  }
  function renderItem(it) {
    var el = document.createElement('article');
    el.className = 'feed-item';
    el.dataset.id = it.id;

    var head =
      '<div class="feed-head">' +
        avatar(it.user) +
        '<div style="flex:1;min-width:0;">' +
          '<div><span class="name">' + escapeHtml(it.user.name) + '</span>' + roleBadge(it.user.role) + '</div>' +
          '<div class="meta">' + typeChip(it.type, it.course);
    if (it.type === 'course_message' && it.course) {
      head += ' · ' + courseLink(it.course);
    }
    head += ' · ' + escapeHtml(it.time_human) + '</div>' +
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

    el.innerHTML = head + body;
    return el;
  }

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
