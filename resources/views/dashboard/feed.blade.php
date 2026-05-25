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
  .composer-actions { display: flex; align-items: center; justify-content: space-between; margin-top: 10px; gap: 8px; }
  .composer-actions .left { display: flex; align-items: center; gap: 10px; min-width: 0; }
  .composer-actions .btn { padding: 9px 22px; }
  .composer-error { color: var(--danger); font-size: 12px; margin-top: 6px; }
  .composer-attach-btn { background: none; border: none; width: 36px; height: 36px; border-radius: 50%; color: var(--muted); cursor: pointer; display: inline-flex; align-items: center; justify-content: center; font-size: 18px; }
  .composer-attach-btn:hover { background: var(--hover); color: var(--accent); }
  .composer-attach-btn:disabled { cursor: default; opacity: 0.5; }
  .composer-upload-status { color: var(--muted); font-size: 13px; display: inline-flex; align-items: center; gap: 6px; }
  .composer-upload-status .fa-spinner { color: var(--accent); }
  .composer-preview { margin-top: 10px; display: inline-flex; position: relative; }
  .composer-preview img { max-height: 140px; max-width: 100%; border-radius: 10px; display: block; }
  .composer-preview-remove { position: absolute; top: 4px; right: 4px; width: 24px; height: 24px; border-radius: 50%; background: rgba(0,0,0,0.6); color: #fff; border: none; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; font-size: 12px; }
  .composer-preview-remove:hover { background: rgba(0,0,0,0.8); }
  .feed-image { margin-top: 10px; }
  .feed-image img { max-width: 100%; max-height: 520px; border-radius: 10px; display: block; cursor: zoom-in; }

  /* Feed items */
  .feed-list { display: flex; flex-direction: column; gap: 14px; }
  .feed-item { background: #fff; border-radius: 12px; box-shadow: 0 1px 2px rgba(0,0,0,0.08); padding: 14px 16px; position: relative; }

  /* Author menu (top-right kebab) */
  .feed-menu { position: absolute; top: 8px; right: 8px; }
  .feed-menu-btn { background: none; border: none; width: 30px; height: 30px; border-radius: 50%; color: var(--muted); cursor: pointer; display: inline-flex; align-items: center; justify-content: center; font-size: 14px; }
  .feed-menu-btn:hover { background: var(--hover); color: var(--text); }
  .feed-menu-dd { position: absolute; top: calc(100% + 4px); right: 0; background: #fff; border-radius: 8px; box-shadow: 0 6px 18px rgba(0,0,0,0.16); min-width: 140px; padding: 4px; z-index: 50; display: none; }
  .feed-menu.open .feed-menu-dd { display: block; }
  .feed-menu-dd button { width: 100%; text-align: left; background: none; border: none; padding: 8px 12px; border-radius: 6px; cursor: pointer; font-family: inherit; font-size: 14px; color: var(--text); display: flex; align-items: center; gap: 10px; }
  .feed-menu-dd button:hover { background: var(--hover); }
  .feed-menu-dd button.danger { color: var(--danger); }
  .feed-menu-dd button.danger:hover { background: #fee2e2; }
  .feed-menu-dd i { width: 14px; text-align: center; }

  /* Edit-in-place */
  .feed-edit { margin-top: 10px; }
  .feed-edit textarea { width: 100%; resize: vertical; border: 1px solid var(--border); background: #fff; border-radius: 10px; padding: 10px 12px; font-family: inherit; font-size: 14px; line-height: 1.5; min-height: 60px; }
  .feed-edit textarea:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px rgba(24,119,242,0.15); }
  .feed-edit-actions { display: flex; justify-content: flex-end; gap: 8px; margin-top: 8px; }
  .feed-edit-err { color: var(--danger); font-size: 12px; margin-top: 6px; }
  .feed-head { display: flex; gap: 10px; align-items: flex-start; }
  .feed-head .head-text { flex: 1; min-width: 0; }
  .feed-head .action { line-height: 1.35; word-break: break-word; }
  .feed-head .action a { color: var(--accent); font-weight: 600; text-decoration: none; }
  .feed-head .action a:hover { text-decoration: underline; }
  .feed-head .meta { color: var(--muted); font-size: 12px; margin-top: 2px; }
  .feed-body { margin-top: 10px; line-height: 1.5; white-space: pre-wrap; word-break: break-word; }
  .feed-body-box { margin-top: 10px; padding: 10px 12px; background: #fafbfc; border-radius: 8px; line-height: 1.5; white-space: pre-wrap; word-break: break-word; }

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
  <h1><i class="fa-solid fa-heart" style="color:var(--accent);margin-right:8px;"></i>Feed</h1>
  @include('partials.header-actions')
</div>

<div class="feed-shell">
  <form id="feedComposer" class="composer" autocomplete="off">
    @csrf
    <textarea id="feedComposerInput" name="body" placeholder="Hvad sker der, {{ explode(' ', trim($user->name))[0] }}?" maxlength="2000" rows="1"></textarea>
    <div id="feedComposerPreview" class="composer-preview" style="display:none;">
      <img id="feedComposerPreviewImg" src="" alt="">
      <button type="button" class="composer-preview-remove" id="feedComposerPreviewRemove" aria-label="Fjern billede"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="composer-actions">
      <div class="left">
        <button type="button" class="composer-attach-btn" id="feedComposerImageBtn" aria-label="Vedhæft billede" title="Vedhæft billede">
          <i class="fa-regular fa-image"></i>
        </button>
        <input type="file" id="feedComposerImageInput" accept="image/jpeg,image/png,image/gif,image/webp" style="display:none;">
        <span id="feedComposerUploadStatus" class="composer-upload-status" style="display:none;">
          <i class="fa-solid fa-spinner fa-spin"></i> Overfører…
        </span>
      </div>
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
  var UPLOAD_URL = '{{ url('/api/feed/upload-image') }}';

  var imageBtn = document.getElementById('feedComposerImageBtn');
  var imageInput = document.getElementById('feedComposerImageInput');
  var uploadStatus = document.getElementById('feedComposerUploadStatus');
  var preview = document.getElementById('feedComposerPreview');
  var previewImg = document.getElementById('feedComposerPreviewImg');
  var previewRemove = document.getElementById('feedComposerPreviewRemove');
  var pendingImagePath = null;
  var uploading = false;

  function escapeHtml(s) { return String(s ?? '').replace(/[&<>"']/g, function (m) { return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[m]; }); }
  function avatar(u) {
    var inner = u.picture_url
      ? '<img src="' + escapeHtml(u.picture_url) + '" alt="">'
      : escapeHtml(u.initials);
    return '<div class="av">' + inner + '</div>';
  }
  function userLink(u) {
    return '<a href="' + escapeHtml(u.profile_url) + '">' + escapeHtml(u.name) + '</a>';
  }
  function courseLink(c, url) {
    return '<a href="' + escapeHtml(url) + '">' + escapeHtml(c.title) + '</a>';
  }
  function renderItem(it) {
    var el = document.createElement('article');
    el.className = 'feed-item';
    el.dataset.id = it.id;
    el.dataset.type = it.type;
    el.dataset.targetId = it.target_id;

    var action = '';
    var body = '';
    var canManage = !!it.mine && (it.type === 'platform_message' || it.type === 'course_message');

    if (it.type === 'enrollment' && it.course) {
      action = userLink(it.user) + ' tilmeldte sig ' + courseLink(it.course, it.course.url);
    } else if (it.type === 'course_message' && it.course) {
      action = userLink(it.user) + ' skrev i ' + courseLink(it.course, it.course.chat_url);
      if (it.body) body = '<div class="feed-body-box">' + escapeHtml(it.body) + '</div>';
    } else {
      // platform_message (or fallback)
      action = userLink(it.user);
      if (it.body) body = '<div class="feed-body">' + escapeHtml(it.body) + '</div>';
      if (it.image_url) {
        body += '<div class="feed-image"><a href="' + escapeHtml(it.image_url) + '" target="_blank" rel="noopener"><img src="' + escapeHtml(it.image_url) + '" alt=""></a></div>';
      }
    }

    var menu = canManage
      ? '<div class="feed-menu">' +
          '<button type="button" class="feed-menu-btn" aria-label="Indstillinger" aria-haspopup="true" aria-expanded="false">' +
            '<i class="fa-solid fa-ellipsis"></i>' +
          '</button>' +
          '<div class="feed-menu-dd" role="menu">' +
            '<button type="button" class="feed-edit-action"><i class="fa-solid fa-pen"></i> Rediger</button>' +
            '<button type="button" class="feed-delete-action danger"><i class="fa-solid fa-trash"></i> Slet</button>' +
          '</div>' +
        '</div>'
      : '';

    var head =
      '<div class="feed-head">' +
        avatar(it.user) +
        '<div class="head-text" style="' + (canManage ? 'padding-right:28px;' : '') + '">' +
          '<div class="action">' + action + '</div>' +
          '<div class="meta">' + escapeHtml(it.time_human) + '</div>' +
        '</div>' +
      '</div>';

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

    el.innerHTML = menu + head + body + footer;
    return el;
  }

  function closeAllMenus(except) {
    list.querySelectorAll('.feed-menu.open').forEach(function (m) {
      if (m !== except) {
        m.classList.remove('open');
        var btn = m.querySelector('.feed-menu-btn');
        if (btn) btn.setAttribute('aria-expanded', 'false');
      }
    });
  }
  document.addEventListener('click', function (e) {
    if (!e.target.closest('.feed-menu')) closeAllMenus(null);
  });

  function startEdit(card) {
    if (card.querySelector('.feed-edit')) return;
    var bodyEl = card.querySelector('.feed-body, .feed-body-box');
    var original = bodyEl ? bodyEl.textContent : '';
    var isBox = bodyEl && bodyEl.classList.contains('feed-body-box');
    var edit = document.createElement('div');
    edit.className = 'feed-edit';
    edit.innerHTML =
      '<textarea maxlength="2000"></textarea>' +
      '<div class="feed-edit-err" style="display:none;"></div>' +
      '<div class="feed-edit-actions">' +
        '<button type="button" class="btn btn-secondary btn-sm feed-edit-cancel">Annullér</button>' +
        '<button type="button" class="btn btn-primary btn-sm feed-edit-save">Gem</button>' +
      '</div>';
    var ta = edit.querySelector('textarea');
    ta.value = original;
    if (bodyEl) { bodyEl.style.display = 'none'; bodyEl.parentNode.insertBefore(edit, bodyEl.nextSibling); }
    else { card.querySelector('.feed-head').insertAdjacentElement('afterend', edit); }
    ta.focus();
    ta.setSelectionRange(ta.value.length, ta.value.length);

    edit.querySelector('.feed-edit-cancel').addEventListener('click', function () {
      edit.remove();
      if (bodyEl) bodyEl.style.display = '';
    });
    edit.querySelector('.feed-edit-save').addEventListener('click', async function () {
      var newBody = ta.value.trim();
      var errBox = edit.querySelector('.feed-edit-err');
      errBox.style.display = 'none';
      if (!newBody) { errBox.textContent = 'Skriv noget tekst.'; errBox.style.display = 'block'; return; }
      var saveBtn = edit.querySelector('.feed-edit-save');
      saveBtn.disabled = true;
      try {
        var id = card.dataset.targetId;
        var res = await fetch('{{ url('/api/messages') }}/' + encodeURIComponent(id), {
          method: 'POST',
          headers: { 'X-CSRF-TOKEN': CSRF, Accept: 'application/json', 'Content-Type': 'application/json' },
          body: JSON.stringify({ body: newBody }),
        });
        if (!res.ok) throw new Error('Save failed');
        var data = await res.json();
        if (bodyEl) {
          bodyEl.textContent = data.body;
          bodyEl.style.display = '';
        } else {
          var newEl = document.createElement('div');
          newEl.className = isBox ? 'feed-body-box' : 'feed-body';
          newEl.textContent = data.body;
          edit.parentNode.insertBefore(newEl, edit);
        }
        edit.remove();
      } catch (err) {
        errBox.textContent = 'Kunne ikke gemme. Prøv igen.';
        errBox.style.display = 'block';
        saveBtn.disabled = false;
      }
    });
  }

  async function doDelete(card) {
    if (!confirm('Slet dette opslag?')) return;
    var id = card.dataset.targetId;
    try {
      var res = await fetch('{{ url('/api/messages') }}/' + encodeURIComponent(id) + '/delete', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': CSRF, Accept: 'application/json' },
      });
      if (!res.ok) throw new Error('Delete failed');
      seen.delete(card.dataset.id);
      card.remove();
      if (!list.children.length) empty.style.display = 'block';
    } catch (err) {
      alert('Kunne ikke slette. Prøv igen.');
    }
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
    var menuBtn = e.target.closest('.feed-menu-btn');
    if (menuBtn) {
      e.preventDefault();
      e.stopPropagation();
      var menu = menuBtn.closest('.feed-menu');
      var wasOpen = menu.classList.contains('open');
      closeAllMenus(null);
      if (!wasOpen) {
        menu.classList.add('open');
        menuBtn.setAttribute('aria-expanded', 'true');
      }
      return;
    }
    var editAct = e.target.closest('.feed-edit-action');
    if (editAct) {
      e.preventDefault();
      closeAllMenus(null);
      startEdit(editAct.closest('.feed-item'));
      return;
    }
    var delAct = e.target.closest('.feed-delete-action');
    if (delAct) {
      e.preventDefault();
      closeAllMenus(null);
      doDelete(delAct.closest('.feed-item'));
      return;
    }
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

  function refreshSubmitState() {
    var hasText = input.value.trim().length > 0;
    submit.disabled = uploading || (!hasText && !pendingImagePath);
  }
  function autosize() {
    input.style.height = 'auto';
    input.style.height = Math.min(input.scrollHeight, 240) + 'px';
    refreshSubmitState();
  }
  input.addEventListener('input', autosize);

  function clearImage() {
    pendingImagePath = null;
    preview.style.display = 'none';
    previewImg.src = '';
    imageInput.value = '';
    refreshSubmitState();
  }
  previewRemove.addEventListener('click', clearImage);
  imageBtn.addEventListener('click', function () {
    if (uploading) return;
    imageInput.click();
  });
  imageInput.addEventListener('change', async function () {
    var file = imageInput.files && imageInput.files[0];
    if (!file) return;
    errBox.style.display = 'none';
    uploading = true;
    imageBtn.disabled = true;
    uploadStatus.style.display = 'inline-flex';
    refreshSubmitState();
    try {
      var fd = new FormData();
      fd.append('image', file);
      var res = await fetch(UPLOAD_URL, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': CSRF, Accept: 'application/json' },
        body: fd,
      });
      if (!res.ok) throw new Error('Upload failed');
      var data = await res.json();
      pendingImagePath = data.path;
      previewImg.src = data.url;
      preview.style.display = 'inline-flex';
    } catch (err) {
      errBox.textContent = 'Kunne ikke uploade billedet. Prøv igen.';
      errBox.style.display = 'block';
      imageInput.value = '';
    } finally {
      uploading = false;
      imageBtn.disabled = false;
      uploadStatus.style.display = 'none';
      refreshSubmitState();
    }
  });
  input.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); composer.requestSubmit(); }
  });

  composer.addEventListener('submit', async function (e) {
    e.preventDefault();
    var body = input.value.trim();
    if (!body && !pendingImagePath) return;
    if (uploading) return;
    submit.disabled = true;
    errBox.style.display = 'none';
    try {
      var res = await fetch(SEND_URL, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': CSRF, Accept: 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify({ body: body, image_path: pendingImagePath }),
      });
      if (!res.ok) throw new Error('Send failed');
      input.value = '';
      clearImage();
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
