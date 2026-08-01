@extends('layouts.app')
@section('content')

<div class="view-header">
  <h1>
    <a href="{{ route('admin.courses.index') }}" style="color:inherit;"><i class="fa-solid fa-arrow-left" style="font-size:16px;margin-right:8px;"></i></a>
    {{ $course->exists ? 'Rediger hold' : 'Nyt hold' }}
  </h1>
  @include('partials.header-actions')
</div>

@php
  $existingTrainerIds = $course->exists ? $course->trainers->pluck('id')->all() : [];
  $oldTrainerIds = old('trainer_ids', $existingTrainerIds);
  $oldTrainerIds = array_map('intval', is_array($oldTrainerIds) ? $oldTrainerIds : []);
  $trainersPayload = $trainers->map(fn ($t) => [
    'id' => $t->id,
    'name' => $t->name,
    'role' => ['owner' => 'Ejer', 'trainer' => 'Træner'][$t->role] ?? $t->role,
    'email' => $t->email,
    'phone' => $t->phone,
    'picture_url' => $t->pictureUrl(),
    'initials' => $t->initials(),
  ])->values();
  $priceKr = ($course->price_cents ?? 0) / 100;
  $priceKrDisplay = $priceKr == (int) $priceKr ? (string) (int) $priceKr : rtrim(rtrim(number_format($priceKr, 2, '.', ''), '0'), '.');
  // Training times, from old input on a failed submit or the saved slots.
  $slotRows = is_array(old('slots'))
    ? collect(old('slots'))->map(fn ($s) => [
        'weekday' => $s['weekday'] ?? '',
        'start' => $s['start'] ?? '',
        'end' => $s['end'] ?? '',
      ])->filter(fn ($s) => isset(\App\Models\Course::WEEKDAYS[$s['weekday']]))->values()
    : ($course->exists ? $course->orderedSchedules()->map(fn ($s) => [
        'weekday' => $s->weekday,
        'start' => $s->startsAt() ?? '',
        'end' => $s->end_time ? substr((string) $s->end_time, 0, 5) : '',
      ])->values() : collect());
  $videoStatusLabel = [
    'pending' => 'Afventer behandling…',
    'processing' => 'Behandler…',
    'completed' => 'Klar (omkodet)',
    'skipped' => 'Klar',
    'failed' => 'Fejlede',
  ][$course->video_processing_status] ?? null;
@endphp

<div class="course-form-shell">
  <form method="POST" action="{{ $course->exists ? route('admin.courses.update', $course) : route('admin.courses.store') }}" enctype="multipart/form-data" class="course-form">
    @csrf

    <section class="card cf-card">
      <h2 class="cf-card-title">Grundlæggende</h2>
      <div class="form-row">
        <label for="title">Titel</label>
        <input id="title" type="text" name="title" value="{{ old('title', $course->title) }}" required>
      </div>
      <div class="form-row">
        <label for="description">Beskrivelse</label>
        <textarea id="description" name="description" rows="5" required>{{ old('description', $course->description) }}</textarea>
      </div>
    </section>

    <section class="card cf-card">
      <div class="cf-card-head">
        <h2 class="cf-card-title">Trænere</h2>
        <button type="button" class="cf-link-btn" id="openTrainerPicker"><i class="fa-solid fa-magnifying-glass"></i> Find trænere</button>
      </div>
      <div class="trainer-list" id="trainerChips"></div>
      @error('trainer_ids')<div class="cf-error">{{ $message }}</div>@enderror
    </section>

    <section class="card cf-card" id="skemaCard">
      <h2 class="cf-card-title">Skema</h2>

      <div class="sk-list" id="skList"></div>
      <div class="sk-empty" id="skEmpty">Ingen træningstider endnu.</div>

      <div class="sk-add" id="skAdd" hidden>
        <select id="skDay" aria-label="Ugedag">
          @foreach (\App\Models\Course::WEEKDAYS as $code => $name)
            <option value="{{ $code }}">{{ $name }}</option>
          @endforeach
        </select>
        <span class="sk-word">fra</span>
        <input type="time" id="skStart" aria-label="Fra">
        <span class="sk-word">til</span>
        <input type="time" id="skEnd" aria-label="Til">
        <button type="button" class="btn btn-primary btn-sm" id="skConfirm">Tilføj</button>
        <button type="button" class="cf-link-btn" id="skCancel">Annullér</button>
        <div class="cf-error" id="skError" hidden></div>
      </div>

      <button type="button" class="btn btn-secondary btn-sm sk-open" id="skOpen">
        <i class="fa-solid fa-plus"></i> <span id="skOpenLabel">Tilføj træningstid</span>
      </button>

      @error('slots')<div class="cf-error">{{ $message }}</div>@enderror
    </section>

    <section class="card cf-card">
      <h2 class="cf-card-title">Pris &amp; tilmelding</h2>
      <div class="cf-grid-2">
        <div class="form-row">
          <label for="price_kr">Pris (kr/måned)</label>
          <input id="price_kr" type="number" name="price_kr" min="0" step="0.01" value="{{ old('price_kr', $priceKrDisplay) }}" required>
          <div class="hint">Brug 0 hvis holdet er gratis.</div>
        </div>
        <div class="form-row">
          <label for="max_participants">Maks. deltagere</label>
          <input id="max_participants" type="number" name="max_participants" min="1" value="{{ old('max_participants', $course->max_participants ?? 10) }}" required>
        </div>
      </div>
      <div class="cf-switch-stack">
        <label class="switch">
          <input type="checkbox" name="is_active" value="1" {{ old('is_active', $course->is_active) ? 'checked' : '' }}>
          <span class="knob"></span>
          <span>Aktiv (vises på forsiden)</span>
        </label>
        <label class="switch">
          <input type="checkbox" name="free_enrollment" value="1" {{ old('free_enrollment', $course->free_enrollment) ? 'checked' : '' }}>
          <span class="knob"></span>
          <span>Gratis tilmelding (spring Stripe over &mdash; mest til test)</span>
        </label>
      </div>
    </section>

    <section class="card cf-card">
      <h2 class="cf-card-title">Forsidemedie</h2>
      <p class="cf-section-hint">Upload enten et billede eller en video. En video erstatter billedet på listesider og spilles på holdets side.</p>

      <div class="cf-media-grid">
        <div class="cf-media">
          <label for="image" class="cf-media-label"><i class="fa-regular fa-image"></i> Billede</label>
          @if ($course->image_path)
            <div class="cf-media-preview"><img src="{{ $course->imageUrl() }}" alt=""></div>
          @endif
          <input id="image" type="file" name="image" accept="image/*" class="cf-file">
          <div class="hint">JPG, PNG eller WebP.</div>
        </div>

        <div class="cf-media">
          <label for="video" class="cf-media-label"><i class="fa-solid fa-circle-play"></i> Video</label>
          @if ($course->hasVideo() && $course->videoThumbnailUrl())
            <div class="cf-media-preview"><img src="{{ $course->videoThumbnailUrl() }}" alt=""></div>
          @elseif ($course->hasVideo())
            <div class="cf-media-preview cf-media-preview-ph"><i class="fa-solid fa-film"></i></div>
          @endif
          <input id="video" type="file" name="video" accept="video/mp4,video/quicktime,video/webm,video/x-m4v,video/x-matroska,video/avi" class="cf-file">
          <div class="hint">MP4, MOV, AVI, WebM, M4V eller MKV. Maks 500 MB.</div>
          @if ($course->hasVideo())
            <div class="cf-video-meta">
              @if ($videoStatusLabel)<span class="cf-status">{{ $videoStatusLabel }}</span>@endif
              <label class="cf-remove">
                <input type="checkbox" name="remove_video" value="1">
                <span>Fjern videoen ved gem</span>
              </label>
            </div>
          @endif
        </div>
      </div>
    </section>

    <div class="cf-footer">
      <button class="btn btn-primary" type="submit">{{ $course->exists ? 'Gem ændringer' : 'Opret hold' }}</button>
      @if ($course->exists)
        <a href="{{ route('courses.show', $course) }}" class="btn btn-secondary"><i class="fa-regular fa-eye"></i> Vis</a>
        <span class="cf-footer-spacer"></span>
        {{-- Submits the form below, not this one: a <form> nested in a <form> is
             dropped by the parser, which sent this button to update instead. --}}
        <button class="btn btn-danger" type="submit" form="delete-course-form"
          onclick="return confirm('Slet holdet og alle relaterede tilmeldinger?');">
          <i class="fa-solid fa-trash"></i> Slet hold
        </button>
      @endif
    </div>
  </form>

  @if ($course->exists)
    <form id="delete-course-form" method="POST" action="{{ route('admin.courses.destroy', $course) }}">
      @csrf
    </form>
  @endif
</div>

@push('styles')
<style>
  .course-form-shell { max-width: 760px; }

  .cf-card { padding: 20px 22px; }
  .cf-card-title { font-size: 15px; font-weight: 700; margin: 0 0 16px; }
  .cf-section-hint { color: var(--muted); font-size: 13px; margin: -10px 0 16px; }

  .cf-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
  @media (max-width: 600px) { .cf-grid-2 { grid-template-columns: 1fr; } }

  .cf-card-head { display: flex; align-items: baseline; justify-content: space-between; gap: 14px; margin-bottom: 12px; }
  .cf-card-head .cf-card-title { margin-bottom: 0; }
  .cf-link-btn { background: none; border: none; color: var(--accent); cursor: pointer; font: inherit; font-size: 13px; font-weight: 600; padding: 0; text-decoration: underline; }
  .cf-link-btn:hover { text-decoration-thickness: 2px; }
  .cf-link-btn i { margin-right: 4px; }
  .cf-error { color: var(--danger); font-size: 12px; margin-top: 6px; }

  .cf-switch-stack { display: flex; flex-direction: column; gap: 10px; margin-top: 6px; }

  .cf-media-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
  @media (max-width: 600px) { .cf-media-grid { grid-template-columns: 1fr; } }
  .cf-media { display: flex; flex-direction: column; gap: 8px; }
  .cf-media-label { display: inline-flex; align-items: center; gap: 8px; font-weight: 600; font-size: 14px; margin-bottom: 0; }
  .cf-media-label i { color: var(--accent); }
  .cf-media-preview { border-radius: 10px; overflow: hidden; aspect-ratio: 16 / 9; background: #f0f2f5; }
  .cf-media-preview img { width: 100%; height: 100%; object-fit: cover; display: block; }
  .cf-media-preview-ph { display: flex; align-items: center; justify-content: center; color: var(--muted); font-size: 30px; }
  .cf-file { font-size: 13px; }
  .cf-video-meta { display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; padding: 8px 10px; background: #f7f8fa; border-radius: 8px; font-size: 12px; }
  .cf-status { color: var(--muted); }
  .cf-remove { display: inline-flex; align-items: center; gap: 6px; cursor: pointer; color: var(--text); }
  .cf-remove input { accent-color: var(--danger); }

  .cf-footer { display: flex; align-items: center; gap: 10px; padding: 2px 2px 26px; flex-wrap: wrap; }
  .cf-footer-spacer { flex: 1; min-width: 8px; }
  .cf-footer form { margin: 0; }

  .sk-empty { color: var(--muted); font-size: 13px; font-style: italic; margin-bottom: 14px; }
  .sk-list { display: flex; flex-direction: column; }
  .sk-row { display: flex; align-items: center; gap: 12px; padding: 10px 0; border-bottom: 1px solid #f0f2f5; }
  .sk-row:first-child { padding-top: 0; }
  .sk-row .sk-day { flex: 0 0 96px; font-weight: 700; font-size: 14px; }
  .sk-row .sk-time { flex: 1; color: var(--muted); font-size: 14px; }
  .sk-remove { background: none; border: none; color: var(--muted); cursor: pointer; font-size: 14px; padding: 6px 8px; border-radius: 50%; }
  .sk-remove:hover { background: var(--hover); color: var(--danger); }
  .sk-list + .sk-add, .sk-list + .sk-open { margin-top: 14px; }

  .sk-add { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; padding: 12px; background: #f7f8fa; border-radius: 10px; margin-bottom: 12px; }
  .sk-add select, .sk-add input[type=time] { width: auto; }
  .sk-word { color: var(--muted); font-size: 13px; }
  .sk-add .cf-error { flex-basis: 100%; margin-top: 0; }
  .sk-open { align-self: flex-start; }
  @media (max-width: 600px) {
    .sk-add select, .sk-add input[type=time] { flex: 1 1 40%; min-width: 0; }
  }

  .trainer-list { display: flex; flex-direction: column; }
  .tr-empty { color: var(--muted); font-size: 13px; font-style: italic; padding: 4px 0; }
  .trainer-row { display: flex; align-items: center; gap: 12px; padding: 12px 0; border-top: 1px solid #f0f2f5; }
  .trainer-row:first-child { border-top: none; padding-top: 2px; }
  .tr-av { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; flex-shrink: 0; font-size: 14px; }
  .tr-meta { flex: 1; min-width: 0; }
  .tr-name { font-weight: 700; line-height: 1.25; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
  .tr-role { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.4px; padding: 2px 8px; border-radius: 999px; background: var(--accent-soft); color: var(--accent); }
  .tr-contact { color: var(--muted); font-size: 13px; margin-top: 2px; overflow-wrap: anywhere; }
  .tr-contact a { color: var(--muted); }
  .tr-contact a:hover { color: var(--accent); text-decoration: underline; }
  .tr-dot { margin: 0 6px; }
  .tr-nocontact { font-style: italic; }
  .tr-remove { background: none; border: none; color: var(--muted); cursor: pointer; font-size: 15px; padding: 8px; border-radius: 50%; flex-shrink: 0; }
  .tr-remove:hover { background: var(--hover); color: var(--danger); }

  .pick-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,0.45); z-index: 9998; display: none; align-items: center; justify-content: center; padding: 20px; }
  .pick-backdrop.open { display: flex; }
  .pick-modal { background: #fff; border-radius: 12px; width: 100%; max-width: 480px; max-height: 80vh; display: flex; flex-direction: column; box-shadow: 0 10px 32px rgba(0,0,0,0.22); overflow: hidden; }
  .pick-head { padding: 14px 18px; border-bottom: 1px solid #f0f2f5; display: flex; align-items: center; gap: 10px; }
  .pick-head .title { font-weight: 700; flex: 1; font-size: 15px; }
  .pick-close { background: none; border: none; cursor: pointer; padding: 6px 10px; border-radius: 6px; color: var(--muted); font-size: 16px; }
  .pick-close:hover { background: var(--hover); color: var(--text); }
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
  .pick-empty { padding: 24px; text-align: center; color: var(--muted); font-size: 13px; }
  .pick-foot { padding: 12px 14px; border-top: 1px solid #f0f2f5; display: flex; gap: 8px; align-items: center; }
  .pick-foot .count { flex: 1; color: var(--muted); font-size: 13px; }
</style>
@endpush

<div class="pick-backdrop" id="trainerPickBackdrop" role="dialog" aria-modal="true">
  <div class="pick-modal">
    <div class="pick-head">
      <div class="title">Vælg trænere</div>
      <button type="button" class="pick-close" id="trainerPickClose" aria-label="Luk"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="pick-search">
      <input type="text" id="trainerPickSearch" placeholder="Søg…" autocomplete="off">
    </div>
    <div class="pick-body" id="trainerPickBody"></div>
    <div class="pick-foot">
      <span class="count" id="trainerPickCount">0 valgt</span>
      <button type="button" class="btn btn-ghost btn-sm" id="trainerPickCancel">Annullér</button>
      <button type="button" class="btn btn-primary btn-sm" id="trainerPickAdd">Tilføj</button>
    </div>
  </div>
</div>

@push('scripts')
<script>
// Skema: training times are added one at a time — day, from, to — and listed
// as they accumulate. A course can run on several days at different hours, or
// twice on the same day.
(function () {
  var card = document.getElementById('skemaCard');
  if (!card) return;

  var DAYS = @json(\App\Models\Course::WEEKDAYS);
  var ORDER = Object.keys(DAYS);
  var slots = @json($slotRows);

  var list = document.getElementById('skList');
  var empty = document.getElementById('skEmpty');
  var addBox = document.getElementById('skAdd');
  var openBtn = document.getElementById('skOpen');
  var openLabel = document.getElementById('skOpenLabel');
  var daySel = document.getElementById('skDay');
  var startIn = document.getElementById('skStart');
  var endIn = document.getElementById('skEnd');
  var errorEl = document.getElementById('skError');

  function escapeHtml(s) { return String(s ?? '').replace(/[&<>"']/g, function (m) { return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]; }); }

  function timeText(s) {
    if (!s.start && !s.end) return 'Ingen fast tid';
    if (s.start && s.end) return s.start + '–' + s.end;
    return s.start || s.end;
  }

  function sortSlots() {
    slots.sort(function (a, b) {
      var d = ORDER.indexOf(a.weekday) - ORDER.indexOf(b.weekday);
      return d !== 0 ? d : String(a.start || '').localeCompare(String(b.start || ''));
    });
  }

  function render() {
    sortSlots();
    list.innerHTML = slots.map(function (s, i) {
      return '<div class="sk-row">' +
        '<span class="sk-day">' + escapeHtml(DAYS[s.weekday] || s.weekday) + '</span>' +
        '<span class="sk-time">' + escapeHtml(timeText(s)) + '</span>' +
        '<button type="button" class="sk-remove" data-i="' + i + '" aria-label="Fjern træningstid"><i class="fa-solid fa-xmark"></i></button>' +
        '<input type="hidden" name="slots[' + i + '][weekday]" value="' + escapeHtml(s.weekday) + '">' +
        '<input type="hidden" name="slots[' + i + '][start]" value="' + escapeHtml(s.start || '') + '">' +
        '<input type="hidden" name="slots[' + i + '][end]" value="' + escapeHtml(s.end || '') + '">' +
      '</div>';
    }).join('');

    list.querySelectorAll('.sk-remove').forEach(function (b) {
      b.addEventListener('click', function () { slots.splice(parseInt(b.dataset.i, 10), 1); render(); });
    });

    empty.hidden = slots.length > 0;
    openLabel.textContent = slots.length ? 'Tilføj træningstid for dette hold' : 'Tilføj træningstid';
  }

  function showError(msg) { errorEl.textContent = msg; errorEl.hidden = false; }

  function openAdd() {
    addBox.hidden = false;
    openBtn.hidden = true;
    errorEl.hidden = true;
    startIn.value = '';
    endIn.value = '';
    daySel.focus();
  }

  function closeAdd() {
    addBox.hidden = true;
    openBtn.hidden = false;
    errorEl.hidden = true;
  }

  openBtn.addEventListener('click', openAdd);
  document.getElementById('skCancel').addEventListener('click', closeAdd);

  document.getElementById('skConfirm').addEventListener('click', function () {
    var day = daySel.value;
    var start = startIn.value;
    var end = endIn.value;

    if (end && !start) return showError('Vælg et starttidspunkt.');
    if (start && end && end <= start) return showError('Sluttidspunktet skal ligge efter starttidspunktet.');
    var clash = slots.some(function (s) { return s.weekday === day && (s.start || '') === (start || ''); });
    if (clash) return showError('Den træningstid er allerede tilføjet.');

    slots.push({ weekday: day, start: start, end: end });
    render();
    closeAdd();
  });

  render();
  // A course with no times yet opens straight into the add row.
  if (!slots.length) openAdd();
})();
</script>
<script>
(function () {
  var ALL = @json($trainersPayload);
  var initial = @json($oldTrainerIds);
  var byId = {};
  ALL.forEach(function (t) { byId[t.id] = t; });

  var chips = document.getElementById('trainerChips');
  var openBtn = document.getElementById('openTrainerPicker');
  var backdrop = document.getElementById('trainerPickBackdrop');
  var closeBtn = document.getElementById('trainerPickClose');
  var cancelBtn = document.getElementById('trainerPickCancel');
  var addBtn = document.getElementById('trainerPickAdd');
  var body = document.getElementById('trainerPickBody');
  var search = document.getElementById('trainerPickSearch');
  var countEl = document.getElementById('trainerPickCount');

  var selected = {};
  initial.forEach(function (id) { if (byId[id]) selected[id] = byId[id]; });
  var pending = {};

  function escapeHtml(s) { return String(s ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }
  function avatarHtml(t) {
    if (t.picture_url) return '<img class="tr-av" src="' + escapeHtml(t.picture_url) + '" alt="">';
    return '<div class="av sm tr-av">' + escapeHtml(t.initials || '?') + '</div>';
  }

  function contactHtml(t) {
    var parts = [];
    if (t.email) parts.push('<a href="mailto:' + escapeHtml(t.email) + '">' + escapeHtml(t.email) + '</a>');
    if (t.phone) parts.push('<a href="tel:' + escapeHtml(t.phone) + '">' + escapeHtml(t.phone) + '</a>');
    if (!parts.length) return '<span class="tr-nocontact">Ingen kontaktoplysninger</span>';
    return parts.join('<span class="tr-dot">·</span>');
  }

  function renderChips() {
    var ids = Object.keys(selected);
    if (!ids.length) {
      chips.innerHTML = '<div class="tr-empty">Ingen trænere valgt endnu.</div>';
      return;
    }
    chips.innerHTML = ids.map(function (id) {
      var t = selected[id];
      return '<div class="trainer-row">' +
        avatarHtml(t) +
        '<div class="tr-meta">' +
          '<div class="tr-name">' + escapeHtml(t.name) + '<span class="tr-role">' + escapeHtml(t.role) + '</span></div>' +
          '<div class="tr-contact">' + contactHtml(t) + '</div>' +
        '</div>' +
        '<button type="button" class="tr-remove" data-remove="' + id + '" aria-label="Fjern ' + escapeHtml(t.name) + '"><i class="fa-solid fa-xmark"></i></button>' +
        '<input type="hidden" name="trainer_ids[]" value="' + id + '">' +
      '</div>';
    }).join('');
    chips.querySelectorAll('button[data-remove]').forEach(function (b) {
      b.addEventListener('click', function () { delete selected[b.dataset.remove]; renderChips(); });
    });
  }

  function openPicker() {
    pending = Object.assign({}, selected);
    search.value = '';
    backdrop.classList.add('open');
    renderList();
    updateCount();
    setTimeout(function () { search.focus(); }, 50);
  }
  function closePicker() { backdrop.classList.remove('open'); }

  function renderList() {
    var q = search.value.trim().toLowerCase();
    var matches = ALL.filter(function (t) { return !q || t.name.toLowerCase().indexOf(q) !== -1; });
    if (!matches.length) {
      body.innerHTML = '<div class="pick-empty">Ingen match.</div>';
      return;
    }
    body.innerHTML = matches.map(function (t) {
      var isSel = !!pending[t.id];
      var left = t.picture_url
        ? '<div class="av sm"><img src="' + escapeHtml(t.picture_url) + '"></div>'
        : '<div class="av sm">' + escapeHtml(t.initials || '?') + '</div>';
      return '<label class="pick-row ' + (isSel ? 'selected' : '') + '" data-id="' + t.id + '">' +
        '<input type="checkbox" ' + (isSel ? 'checked' : '') + '>' +
        left +
        '<div class="meta"><div class="nm">' + escapeHtml(t.name) + '</div><div class="sub">' + escapeHtml(t.role) + (t.email ? ' · ' + escapeHtml(t.email) : '') + '</div></div>' +
        '</label>';
    }).join('');
    body.querySelectorAll('.pick-row').forEach(function (row) {
      var cb = row.querySelector('input[type=checkbox]');
      var t = byId[parseInt(row.dataset.id, 10)];
      cb.addEventListener('change', function () {
        if (cb.checked) { pending[t.id] = t; row.classList.add('selected'); }
        else { delete pending[t.id]; row.classList.remove('selected'); }
        updateCount();
      });
    });
  }

  function updateCount() { countEl.textContent = Object.keys(pending).length + ' valgt'; }

  openBtn.addEventListener('click', openPicker);
  closeBtn.addEventListener('click', closePicker);
  cancelBtn.addEventListener('click', closePicker);
  backdrop.addEventListener('click', function (e) { if (e.target === backdrop) closePicker(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && backdrop.classList.contains('open')) closePicker(); });
  search.addEventListener('input', renderList);
  addBtn.addEventListener('click', function () {
    selected = Object.assign({}, pending);
    renderChips();
    closePicker();
  });

  renderChips();
})();
</script>
@endpush

@endsection
