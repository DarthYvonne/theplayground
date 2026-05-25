@extends('layouts.app')
@section('content')

@push('styles')
<style>
  .besk-shell { max-width: 720px; }

  .besk-toolbar { display: flex; gap: 10px; align-items: center; margin-bottom: 14px; flex-wrap: wrap; }
  .besk-toolbar .email-toggle { margin-left: auto; display: inline-flex; align-items: center; gap: 8px; background: #fff; padding: 8px 12px; border-radius: 8px; box-shadow: 0 1px 2px rgba(0,0,0,0.06); font-size: 13px; color: var(--muted); }
  .besk-toolbar .email-toggle .switch { gap: 6px; }

  .thread-list { background: #fff; border-radius: 12px; box-shadow: 0 1px 2px rgba(0,0,0,0.08); overflow: hidden; }
  .thread-row { display: flex; gap: 12px; align-items: center; padding: 14px 16px; color: var(--text); }
  .thread-row + .thread-row { border-top: 1px solid #f0f2f5; }
  .thread-row:hover { background: var(--hover); }
  .thread-row .info { flex: 1; min-width: 0; }
  .thread-row .name-row { display: flex; align-items: baseline; gap: 8px; }
  .thread-row .name { font-weight: 700; }
  .thread-row .time { color: var(--muted); font-size: 12px; margin-left: auto; }
  .thread-row .snippet { color: var(--muted); font-size: 13px; margin-top: 2px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .thread-row.unread .snippet { color: var(--text); font-weight: 600; }
  .thread-row .unread-dot { width: 9px; height: 9px; background: var(--accent); border-radius: 50%; display: inline-block; flex-shrink: 0; }
  .thread-row .course-tag { display: inline-flex; align-items: center; gap: 4px; background: var(--accent-soft); color: var(--accent); font-size: 11px; font-weight: 600; padding: 1px 7px; border-radius: 10px; }

  .besk-empty { background: #fff; border-radius: 12px; padding: 60px 24px; text-align: center; color: var(--muted); box-shadow: 0 1px 2px rgba(0,0,0,0.08); }
  .besk-empty .icon { font-size: 36px; color: var(--accent); opacity: 0.55; margin-bottom: 10px; }
  .besk-empty h3 { color: var(--text); margin-bottom: 6px; font-size: 17px; }

  /* Compose */
  .compose-card { background: #fff; border-radius: 12px; box-shadow: 0 1px 2px rgba(0,0,0,0.08); padding: 16px 18px; margin-bottom: 14px; }
  .compose-card h2 { font-size: 14px; font-weight: 700; margin-bottom: 10px; }
  .compose-row { margin-bottom: 12px; }
  .compose-row > label { display: inline-block; margin-bottom: 6px; font-size: 13px; font-weight: 600; }
  .compose-row .find-link { margin-left: 8px; font-size: 13px; color: var(--accent); font-weight: 600; background: none; border: none; cursor: pointer; padding: 0; text-decoration: underline; }
  .compose-row .find-link:hover { text-decoration-thickness: 2px; }
  .chips-area { display: flex; flex-wrap: wrap; gap: 6px; min-height: 28px; margin-top: 12px; }
  .chips-area:empty::before { content: 'Ingen modtagere valgt.'; color: var(--muted); font-size: 13px; font-style: italic; }
  .pill { display: inline-flex; align-items: center; gap: 6px; background: var(--accent-soft); color: var(--accent); padding: 4px 8px 4px 4px; border-radius: 999px; font-weight: 600; font-size: 13px; }
  .pill button { background: none; border: none; color: inherit; cursor: pointer; font-size: 14px; padding: 0 2px; line-height: 1; }
  .pill .av { width: 22px; height: 22px; font-size: 10px; }
  .pill.course { background: var(--accent-soft); color: var(--accent); padding-left: 8px; }

  .compose-actions { display: flex; gap: 8px; align-items: center; }
  .compose-actions .hint { color: var(--muted); font-size: 12px; }

  /* Recipient picker modal */
  .pick-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,0.45); z-index: 9998; display: none; align-items: center; justify-content: center; padding: 20px; }
  .pick-backdrop.open { display: flex; }
  .pick-modal { background: #fff; border-radius: 12px; width: 100%; max-width: 480px; max-height: 80vh; display: flex; flex-direction: column; box-shadow: 0 10px 32px rgba(0,0,0,0.22); overflow: hidden; }
  .pick-head { padding: 14px 18px; border-bottom: 1px solid #f0f2f5; display: flex; align-items: center; gap: 10px; }
  .pick-head .title { font-weight: 700; flex: 1; font-size: 15px; }
  .pick-close { background: none; border: none; cursor: pointer; padding: 6px 10px; border-radius: 6px; color: var(--muted); font-size: 16px; }
  .pick-close:hover { background: var(--hover); color: var(--text); }
  .pick-tabs { display: flex; border-bottom: 1px solid #f0f2f5; }
  .pick-tab { flex: 1; background: none; border: none; cursor: pointer; padding: 12px 16px; font-size: 14px; font-weight: 600; color: var(--muted); border-bottom: 2px solid transparent; }
  .pick-tab.active { color: var(--accent); border-bottom-color: var(--accent); }
  .pick-tab:hover:not(.active) { color: var(--text); }
  .pick-search { padding: 10px 14px; border-bottom: 1px solid #f0f2f5; }
  .pick-search input { width: 100%; padding: 8px 12px; border: 1px solid var(--border); border-radius: 8px; font-size: 14px; }
  .pick-body { flex: 1; overflow-y: auto; padding: 6px 0; }
  .pick-row { display: flex; gap: 10px; align-items: center; padding: 8px 14px; cursor: pointer; font-size: 14px; }
  .pick-row:hover { background: var(--hover); }
  .pick-row.selected { background: var(--accent-soft); }
  .pick-row input[type=checkbox] { flex-shrink: 0; width: 16px; height: 16px; accent-color: var(--accent); cursor: pointer; }
  .pick-row .meta { flex: 1; min-width: 0; }
  .pick-row .nm { font-weight: 600; line-height: 1.2; }
  .pick-row .sub { color: var(--muted); font-size: 12px; margin-top: 1px; }
  .pick-row .course-icon { width: 32px; height: 32px; border-radius: 8px; background: var(--accent-soft); color: var(--accent); display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 14px; }
  .pick-empty, .pick-loading { padding: 24px; text-align: center; color: var(--muted); font-size: 13px; }
  .pick-foot { padding: 12px 14px; border-top: 1px solid #f0f2f5; display: flex; gap: 8px; align-items: center; }
  .pick-foot .count { flex: 1; color: var(--muted); font-size: 13px; }
</style>
@endpush

<div class="view-header">
  <h1><i class="fa-regular fa-envelope" style="color:var(--text);margin-right:8px;"></i>Beskeder</h1>
  @include('partials.header-actions')
</div>

<div class="besk-shell">

  <div class="besk-toolbar">
    <button type="button" class="btn btn-primary" id="composeOpen"><i class="fa-regular fa-pen-to-square"></i> Ny besked</button>
    <form method="POST" action="{{ route('beskeder.settings') }}" class="email-toggle">
      @csrf
      <span>Notificer mig (email)</span>
      <label class="switch">
        <input type="checkbox" name="email_on_message" value="1" onchange="this.form.submit()" {{ auth()->user()->email_on_message ? 'checked' : '' }}>
        <span class="knob"></span>
      </label>
    </form>
  </div>

  @php
    $hasPrefill = $prefill || $prefillCourse;
    $prefillData = [
      'users' => $prefill ? [[
        'id' => $prefill->id,
        'label' => $prefill->name,
        'picture_url' => $prefill->pictureUrl(),
        'initials' => $prefill->initials(),
      ]] : [],
      'courses' => $prefillCourse ? [[
        'id' => $prefillCourse->id,
        'label' => $prefillCourse->title,
      ]] : [],
    ];
  @endphp

  <div class="compose-card" id="composeCard" @if (!$hasPrefill) style="display:none;" @endif>
    <h2>Ny besked</h2>
    <form method="POST" action="{{ route('beskeder.store') }}" id="composeForm">
      @csrf

      <div class="compose-row">
        <label>Til</label>
        <button type="button" class="find-link" id="openPicker"><i class="fa-solid fa-magnifying-glass"></i> Find modtagere</button>
        <div class="chips-area" id="chips"></div>
      </div>

      <div class="form-row">
        <label for="composeBody">Besked</label>
        <textarea id="composeBody" name="body" rows="5" maxlength="8000" required placeholder="Skriv din besked…"></textarea>
      </div>

      <div class="compose-actions">
        <button type="submit" class="btn btn-primary" id="composeSend"><i class="fa-solid fa-paper-plane"></i> Send</button>
        <button type="button" class="btn btn-ghost btn-sm" id="composeCancel">Annullér</button>
        <span class="hint" id="composeHint"></span>
      </div>
    </form>
  </div>

  @auth
  <div class="pick-backdrop" id="pickBackdrop" role="dialog" aria-modal="true">
    <div class="pick-modal">
      <div class="pick-head">
        <div class="title">Vælg modtagere</div>
        <button type="button" class="pick-close" id="pickClose" aria-label="Luk"><i class="fa-solid fa-xmark"></i></button>
      </div>
      <div class="pick-tabs">
        <button type="button" class="pick-tab active" data-tab="user">Medlemmer</button>
        @if ($canBroadcast)
          <button type="button" class="pick-tab" data-tab="course">Hold</button>
        @endif
      </div>
      <div class="pick-search">
        <input type="text" id="pickSearch" placeholder="Søg…" autocomplete="off">
      </div>
      <div class="pick-body" id="pickBody"><div class="pick-loading">Indlæser…</div></div>
      <div class="pick-foot">
        <span class="count" id="pickCount">0 valgt</span>
        <button type="button" class="btn btn-ghost btn-sm" id="pickCancel">Annullér</button>
        <button type="button" class="btn btn-primary btn-sm" id="pickAdd">Tilføj</button>
      </div>
    </div>
  </div>
  @endauth

  @if (empty($threads))
    <div class="besk-empty">
      <div class="icon"><i class="fa-regular fa-envelope-open"></i></div>
      <h3>Ingen beskeder endnu</h3>
      <p>Klik <strong>Ny besked</strong> for at skrive til en person@if ($canBroadcast) eller et helt hold@endif.</p>
    </div>
  @else
    <div class="thread-list">
      @foreach ($threads as $t)
        <a class="thread-row {{ $t['unread'] > 0 ? 'unread' : '' }}" href="{{ route('beskeder.show', $t['user']) }}">
          @include('partials.avatar', ['u' => $t['user']])
          <div class="info">
            <div class="name-row">
              <span class="name">{{ $t['user']->name }}</span>
              @if ($t['last']->viaCourse)
                <span class="course-tag" title="Sendt via Hold-besked"><i class="fa-solid fa-dumbbell"></i> {{ $t['last']->viaCourse->title }}</span>
              @endif
              <span class="time">{{ $t['last']->created_at->diffForHumans(null, true) }}</span>
            </div>
            <div class="snippet">
              @if ($t['last_mine']) <span style="color:var(--muted);font-weight:500;">Du:</span> @endif
              {{ \Illuminate\Support\Str::limit($t['last']->body, 110) }}
            </div>
          </div>
          @if ($t['unread'] > 0)
            <span class="unread-dot" title="{{ $t['unread'] }} ulæst{{ $t['unread'] === 1 ? '' : 'e' }}"></span>
          @endif
        </a>
      @endforeach
    </div>
  @endif

</div>

@push('scripts')
<script>
(function () {
  var RECIPIENTS_URL = '{{ route('beskeder.recipients') }}';
  var openBtn = document.getElementById('composeOpen');
  var cancelBtn = document.getElementById('composeCancel');
  var card = document.getElementById('composeCard');
  var form = document.getElementById('composeForm');
  var hint = document.getElementById('composeHint');
  var body = document.getElementById('composeBody');
  var chips = document.getElementById('chips');
  var openPicker = document.getElementById('openPicker');

  // selected[type][id] = {label, picture_url, initials}
  var selected = { user: {}, course: {} };

  function escapeHtml(s) { return String(s ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }

  function avatarHtml(item) {
    if (item.picture_url) {
      return '<img src="' + escapeHtml(item.picture_url) + '" style="width:22px;height:22px;border-radius:50%;object-fit:cover;">';
    }
    return '<div class="av sm" style="width:22px;height:22px;font-size:10px;">' + escapeHtml(item.initials || '?') + '</div>';
  }

  function renderChips() {
    var html = '';
    Object.keys(selected.course).forEach(function (id) {
      var c = selected.course[id];
      html += '<span class="pill course"><i class="fa-solid fa-dumbbell"></i> <span>' + escapeHtml(c.label) +
        '</span><button type="button" data-remove="course" data-id="' + id + '" aria-label="Fjern">×</button>' +
        '<input type="hidden" name="recipient_courses[]" value="' + id + '"></span>';
    });
    Object.keys(selected.user).forEach(function (id) {
      var u = selected.user[id];
      html += '<span class="pill">' + avatarHtml(u) + '<span>' + escapeHtml(u.label) +
        '</span><button type="button" data-remove="user" data-id="' + id + '" aria-label="Fjern">×</button>' +
        '<input type="hidden" name="recipient_users[]" value="' + id + '"></span>';
    });
    chips.innerHTML = html;
    chips.querySelectorAll('button[data-remove]').forEach(function (b) {
      b.addEventListener('click', function () {
        delete selected[b.dataset.remove][b.dataset.id];
        renderChips();
        updateHint();
      });
    });
  }

  function updateHint() {
    hint.textContent = Object.keys(selected.course).length
      ? 'Hold-modtagere får beskeden som en privat besked til hver enkelt.'
      : '';
  }

  function openCompose() { card.style.display = ''; body.focus(); }
  function closeCompose() {
    card.style.display = 'none';
    selected = { user: {}, course: {} };
    renderChips();
    updateHint();
    body.value = '';
  }
  if (openBtn) openBtn.addEventListener('click', openCompose);
  if (cancelBtn) cancelBtn.addEventListener('click', closeCompose);

  // Hydrate prefill (e.g. ?til=X / ?hold=Y from server).
  (function () {
    var pre = @json($prefillData);
    (pre.users || []).forEach(function (u) { selected.user[u.id] = u; });
    (pre.courses || []).forEach(function (c) { selected.course[c.id] = c; });
    renderChips();
    updateHint();
  })();

  form.addEventListener('submit', function (e) {
    if (!Object.keys(selected.user).length && !Object.keys(selected.course).length) {
      e.preventDefault();
      hint.textContent = 'Vælg mindst én modtager.';
      if (openPicker) openPicker.focus();
    }
  });

  // ---------- Picker modal ----------
  var backdrop = document.getElementById('pickBackdrop');
  if (!backdrop) return; // not auth'd, nothing to wire

  var pickClose = document.getElementById('pickClose');
  var pickCancel = document.getElementById('pickCancel');
  var pickAdd = document.getElementById('pickAdd');
  var pickBody = document.getElementById('pickBody');
  var pickSearch = document.getElementById('pickSearch');
  var pickCount = document.getElementById('pickCount');
  var tabs = backdrop.querySelectorAll('.pick-tab');

  var currentTab = 'user';
  var lastResults = []; // for current tab + query
  var pending = { user: {}, course: {} };

  function openPickerModal() {
    pending = {
      user: Object.assign({}, selected.user),
      course: Object.assign({}, selected.course),
    };
    backdrop.classList.add('open');
    pickSearch.value = '';
    updatePickCount();
    loadList();
    setTimeout(function () { pickSearch.focus(); }, 50);
  }
  function closePickerModal() { backdrop.classList.remove('open'); }

  if (openPicker) openPicker.addEventListener('click', openPickerModal);
  pickClose.addEventListener('click', closePickerModal);
  pickCancel.addEventListener('click', closePickerModal);
  backdrop.addEventListener('click', function (e) { if (e.target === backdrop) closePickerModal(); });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && backdrop.classList.contains('open')) closePickerModal();
  });

  tabs.forEach(function (t) {
    t.addEventListener('click', function () {
      tabs.forEach(function (x) { x.classList.remove('active'); });
      t.classList.add('active');
      currentTab = t.dataset.tab;
      pickSearch.value = '';
      loadList();
    });
  });

  var searchTimer = null;
  pickSearch.addEventListener('input', function () {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(loadList, 160);
  });

  async function loadList() {
    pickBody.innerHTML = '<div class="pick-loading">Indlæser…</div>';
    try {
      var url = RECIPIENTS_URL + '?type=' + currentTab + '&q=' + encodeURIComponent(pickSearch.value.trim());
      var res = await fetch(url);
      var data = await res.json();
      lastResults = data.results || [];
      renderList();
    } catch (e) {
      pickBody.innerHTML = '<div class="pick-empty">Kunne ikke indlæse.</div>';
    }
  }

  function renderList() {
    if (!lastResults.length) {
      pickBody.innerHTML = '<div class="pick-empty">Ingen ' + (currentTab === 'course' ? 'hold' : 'medlemmer') + '.</div>';
      return;
    }
    pickBody.innerHTML = lastResults.map(function (r, i) {
      var isSel = !!pending[r.type][r.id];
      var left;
      if (r.type === 'course') {
        left = '<div class="course-icon"><i class="fa-solid fa-dumbbell"></i></div>';
      } else if (r.picture_url) {
        left = '<div class="av sm"><img src="' + escapeHtml(r.picture_url) + '"></div>';
      } else {
        left = '<div class="av sm">' + escapeHtml(r.initials || '?') + '</div>';
      }
      return '<label class="pick-row ' + (isSel ? 'selected' : '') + '" data-i="' + i + '">' +
        '<input type="checkbox" ' + (isSel ? 'checked' : '') + '>' +
        left +
        '<div class="meta"><div class="nm">' + escapeHtml(r.label) + '</div><div class="sub">' + escapeHtml(r.sub || '') + '</div></div>' +
        '</label>';
    }).join('');
    pickBody.querySelectorAll('.pick-row').forEach(function (row) {
      var cb = row.querySelector('input[type=checkbox]');
      var item = lastResults[parseInt(row.dataset.i, 10)];
      cb.addEventListener('change', function () {
        if (cb.checked) {
          pending[item.type][item.id] = item;
          row.classList.add('selected');
        } else {
          delete pending[item.type][item.id];
          row.classList.remove('selected');
        }
        updatePickCount();
      });
    });
  }

  function updatePickCount() {
    var n = Object.keys(pending.user).length + Object.keys(pending.course).length;
    pickCount.textContent = n + ' valgt';
  }

  pickAdd.addEventListener('click', function () {
    selected = {
      user: Object.assign({}, pending.user),
      course: Object.assign({}, pending.course),
    };
    renderChips();
    updateHint();
    closePickerModal();
    body.focus();
  });
})();
</script>
@endpush

@endsection
