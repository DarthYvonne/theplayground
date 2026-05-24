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
  .compose-row { position: relative; margin-bottom: 12px; }
  .compose-row .pill { display: inline-flex; align-items: center; gap: 8px; background: var(--accent-soft); color: var(--accent); padding: 6px 10px 6px 6px; border-radius: 999px; font-weight: 600; font-size: 13px; }
  .compose-row .pill button { background: none; border: none; color: inherit; cursor: pointer; font-size: 14px; padding: 0 2px; line-height: 1; }
  .compose-row .pill .av { width: 22px; height: 22px; font-size: 10px; }
  .compose-row .pill.course { background: #fef3c7; color: #92400e; }

  .ac-results { position: absolute; top: 100%; left: 0; right: 0; background: #fff; border: 1px solid var(--border); border-top: none; border-radius: 0 0 10px 10px; max-height: 280px; overflow-y: auto; z-index: 50; display: none; box-shadow: 0 6px 16px rgba(0,0,0,0.08); }
  .ac-results.open { display: block; }
  .ac-item { display: flex; gap: 10px; align-items: center; padding: 8px 12px; cursor: pointer; font-size: 13px; }
  .ac-item + .ac-item { border-top: 1px solid #f0f2f5; }
  .ac-item:hover, .ac-item.focus { background: var(--accent-soft); }
  .ac-item .sub { color: var(--muted); font-size: 12px; }
  .ac-item .course-icon { width: 32px; height: 32px; border-radius: 8px; background: var(--accent-soft); color: var(--accent); display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 14px; font-weight: 700; }
  .ac-empty { padding: 14px; color: var(--muted); font-size: 13px; text-align: center; }

  .compose-actions { display: flex; gap: 8px; align-items: center; }
  .compose-actions .hint { color: var(--muted); font-size: 12px; }
</style>
@endpush

<div class="view-header">
  <h1><i class="fa-regular fa-envelope" style="color:var(--accent);margin-right:8px;"></i>Beskeder</h1>
  @include('partials.header-actions')
</div>

<div class="besk-shell">

  <div class="besk-toolbar">
    <button type="button" class="btn btn-primary" id="composeOpen"><i class="fa-regular fa-pen-to-square"></i> Ny besked</button>
    <form method="POST" action="{{ route('beskeder.settings') }}" class="email-toggle">
      @csrf
      <span>Notificer via email</span>
      <label class="switch">
        <input type="checkbox" name="email_on_message" value="1" onchange="this.form.submit()" {{ auth()->user()->email_on_message ? 'checked' : '' }}>
        <span class="knob"></span>
      </label>
    </form>
  </div>

  <div class="compose-card" id="composeCard" @if (!$prefill) style="display:none;" @endif>
    <h2>Ny besked</h2>
    <form method="POST" action="{{ route('beskeder.store') }}" id="composeForm">
      @csrf
      <input type="hidden" name="recipient_type" id="recipientType" value="{{ $prefill ? 'user' : '' }}">
      <input type="hidden" name="recipient_id" id="recipientId" value="{{ $prefill->id ?? '' }}">

      <div class="compose-row">
        <label for="recipientSearch">Til</label>
        <div id="recipientChosen" @if (!$prefill) style="display:none;" @endif></div>
        <input type="text" id="recipientSearch" placeholder="Søg efter person@if ($canBroadcast) eller hold@endif…" autocomplete="off" @if ($prefill) style="display:none;" @endif>
        <div class="ac-results" id="acResults"></div>
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
                <span class="course-tag" title="Sendt via Hold-besked"><i class="fa-solid fa-bullhorn"></i> {{ $t['last']->viaCourse->title }}</span>
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
  var openBtn = document.getElementById('composeOpen');
  var cancelBtn = document.getElementById('composeCancel');
  var card = document.getElementById('composeCard');
  var search = document.getElementById('recipientSearch');
  var chosen = document.getElementById('recipientChosen');
  var ac = document.getElementById('acResults');
  var typeF = document.getElementById('recipientType');
  var idF = document.getElementById('recipientId');
  var form = document.getElementById('composeForm');
  var hint = document.getElementById('composeHint');
  var body = document.getElementById('composeBody');

  function escapeHtml(s) { return String(s ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }

  function setRecipient(item) {
    typeF.value = item.type;
    idF.value = item.id;
    var av = item.picture_url
      ? '<img src="' + escapeHtml(item.picture_url) + '" style="width:22px;height:22px;border-radius:50%;object-fit:cover;">'
      : '<div class="av sm" style="width:22px;height:22px;font-size:10px;">' + escapeHtml(item.initials || '?') + '</div>';
    var cls = item.type === 'course' ? 'pill course' : 'pill';
    var icon = item.type === 'course'
      ? '<i class="fa-solid fa-bullhorn" style="margin-right:2px;"></i>'
      : av;
    chosen.innerHTML = '<span class="' + cls + '">' + icon + '<span>' + escapeHtml(item.label) + '</span><button type="button" id="clearRecipient" aria-label="Fjern">×</button></span>';
    if (item.type === 'course') hint.textContent = 'Sendes som privat besked til hver enkelt på holdet.';
    else hint.textContent = '';
    chosen.style.display = '';
    search.style.display = 'none';
    ac.classList.remove('open');
    document.getElementById('clearRecipient').addEventListener('click', clearRecipient);
    body.focus();
  }
  function clearRecipient() {
    typeF.value = ''; idF.value = '';
    chosen.innerHTML = ''; chosen.style.display = 'none';
    hint.textContent = '';
    search.style.display = ''; search.value = '';
    search.focus();
  }

  function openCompose() { card.style.display = ''; search.focus(); }
  function closeCompose() { card.style.display = 'none'; clearRecipient(); body.value = ''; }
  openBtn.addEventListener('click', openCompose);
  cancelBtn.addEventListener('click', closeCompose);

  var t = null;
  search.addEventListener('input', function () {
    clearTimeout(t);
    var q = search.value.trim();
    t = setTimeout(async function () {
      try {
        var res = await fetch('{{ route('beskeder.recipients') }}?q=' + encodeURIComponent(q));
        var data = await res.json();
        if (!data.results.length) {
          ac.innerHTML = '<div class="ac-empty">Ingen match.</div>';
        } else {
          ac.innerHTML = data.results.map(function (r, i) {
            var left;
            if (r.type === 'course') {
              left = '<div class="course-icon"><i class="fa-solid fa-bullhorn"></i></div>';
            } else {
              left = r.picture_url
                ? '<div class="av sm"><img src="' + escapeHtml(r.picture_url) + '"></div>'
                : '<div class="av sm">' + escapeHtml(r.initials) + '</div>';
            }
            return '<div class="ac-item" data-i="' + i + '">' + left +
              '<div><div>' + escapeHtml(r.label) + '</div><div class="sub">' + escapeHtml(r.sub) + '</div></div></div>';
          }).join('');
          ac.querySelectorAll('.ac-item').forEach(function (el) {
            el.addEventListener('click', function () { setRecipient(data.results[parseInt(el.dataset.i, 10)]); });
          });
        }
        ac.classList.add('open');
      } catch (e) {
        ac.innerHTML = '<div class="ac-empty">Kunne ikke søge.</div>';
        ac.classList.add('open');
      }
    }, 160);
  });

  document.addEventListener('click', function (e) {
    if (!ac.classList.contains('open')) return;
    if (ac.contains(e.target) || search.contains(e.target)) return;
    ac.classList.remove('open');
  });

  form.addEventListener('submit', function (e) {
    if (!typeF.value || !idF.value) {
      e.preventDefault();
      hint.textContent = 'Vælg en modtager.';
      search.focus();
    }
  });
})();
</script>
@endpush

@endsection
