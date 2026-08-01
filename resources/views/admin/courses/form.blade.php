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
  $selectedDays = is_array(old('weekdays')) ? old('weekdays') : $course->weekdaysList();

  // One start/end per weekday, from old input on a failed submit or the saved slots.
  $oldSchedule = is_array(old('schedule')) ? old('schedule') : null;
  $savedSlots = $course->exists ? $course->orderedSchedules()->keyBy('weekday') : collect();
  $dayTimes = [];
  foreach (\App\Models\Course::WEEKDAYS as $code => $_) {
    $slot = $savedSlots->get($code);
    $dayTimes[$code] = $oldSchedule !== null
      ? ['start' => $oldSchedule[$code]['start'] ?? '', 'end' => $oldSchedule[$code]['end'] ?? '']
      : ['start' => $slot?->startsAt() ?? '', 'end' => $slot?->end_time ? substr((string) $slot->end_time, 0, 5) : ''];
  }

  // Start in "same time" mode unless the chosen days actually differ.
  $chosenTimes = collect($selectedDays)->map(fn ($d) => $dayTimes[$d] ?? null)->filter()->unique();
  $sameTimeDefault = $chosenTimes->count() <= 1;
  $sharedStart = $sameTimeDefault ? ($chosenTimes->first()['start'] ?? '') : '';
  $sharedEnd = $sameTimeDefault ? ($chosenTimes->first()['end'] ?? '') : '';
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

      <div class="weekday-row">
        @foreach (\App\Models\Course::WEEKDAYS as $code => $name)
          <label class="weekday-chip">
            <input type="checkbox" name="weekdays[]" value="{{ $code }}" {{ in_array($code, $selectedDays, true) ? 'checked' : '' }}>
            <span>{{ $name }}</span>
          </label>
        @endforeach
      </div>

      <div class="sk-empty" id="skEmpty">Vælg de dage holdet træner.</div>

      <div class="sk-body" id="skBody" hidden>
        <label class="sk-same">
          <input type="checkbox" id="skSameTime" {{ $sameTimeDefault ? 'checked' : '' }}>
          <span>Samme tid alle dage</span>
        </label>

        <div class="sk-shared" id="skShared">
          <span class="sk-day">Alle dage</span>
          <input type="time" id="skSharedStart" value="{{ $sharedStart }}" aria-label="Fra, alle dage">
          <span class="sk-dash">–</span>
          <input type="time" id="skSharedEnd" value="{{ $sharedEnd }}" aria-label="Til, alle dage">
        </div>

        <div class="sk-days" id="skDays">
          @foreach (\App\Models\Course::WEEKDAYS as $code => $name)
            <div class="sk-row" data-day="{{ $code }}" hidden>
              <span class="sk-day">{{ $name }}</span>
              <input type="time" name="schedule[{{ $code }}][start]" value="{{ $dayTimes[$code]['start'] }}" aria-label="Fra, {{ $name }}">
              <span class="sk-dash">–</span>
              <input type="time" name="schedule[{{ $code }}][end]" value="{{ $dayTimes[$code]['end'] }}" aria-label="Til, {{ $name }}">
            </div>
          @endforeach
        </div>

        <p class="sk-hint">Lad tiderne stå tomme, hvis holdet ikke har et fast klokkeslæt.</p>
      </div>
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

  .sk-empty { color: var(--muted); font-size: 13px; font-style: italic; margin-top: 14px; }
  .sk-body { margin-top: 16px; padding-top: 14px; border-top: 1px solid #f0f2f5; }
  .sk-same { display: inline-flex; align-items: center; gap: 8px; cursor: pointer; font-size: 13px; font-weight: 600; color: var(--text); margin-bottom: 12px; }
  .sk-same input { width: 15px; height: 15px; accent-color: var(--accent); cursor: pointer; }
  .sk-shared, .sk-row { display: flex; align-items: center; gap: 8px; padding: 6px 0; }
  .sk-row + .sk-row { border-top: 1px solid #f7f8fa; }
  .sk-day { flex: 0 0 96px; font-size: 13px; font-weight: 600; color: var(--text); }
  .sk-shared .sk-day { color: var(--muted); }
  .sk-shared input[type=time], .sk-row input[type=time] { width: auto; flex: 0 1 128px; }
  .sk-dash { color: var(--muted); }
  .sk-hint { color: var(--muted); font-size: 12px; margin-top: 10px; }
  @media (max-width: 600px) {
    .sk-day { flex-basis: 74px; }
    .sk-shared input[type=time], .sk-row input[type=time] { flex: 1 1 0; min-width: 0; }
  }

  .weekday-row { display: flex; flex-wrap: wrap; gap: 6px; }
  .weekday-chip { display: inline-flex; align-items: center; cursor: pointer; user-select: none; font-weight: 500; }
  .weekday-chip input { position: absolute; opacity: 0; pointer-events: none; }
  .weekday-chip span { padding: 8px 14px; border-radius: 999px; border: 1px solid var(--border); background: #fff; font-size: 13px; transition: background 0.1s, border-color 0.1s, color 0.1s; }
  .weekday-chip:hover span { background: var(--hover); }
  .weekday-chip input:checked + span { background: var(--accent); border-color: var(--accent); color: #fff; }

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
// Skema: chips choose the days, each chosen day gets its own hours. "Samme tid
// alle dage" mirrors one pair into every day so the common case stays one entry.
(function () {
  var card = document.getElementById('skemaCard');
  if (!card) return;

  var dayBoxes = card.querySelectorAll('input[name="weekdays[]"]');
  var body = document.getElementById('skBody');
  var empty = document.getElementById('skEmpty');
  var same = document.getElementById('skSameTime');
  var shared = document.getElementById('skShared');
  var sharedStart = document.getElementById('skSharedStart');
  var sharedEnd = document.getElementById('skSharedEnd');
  var rows = {};
  card.querySelectorAll('.sk-row').forEach(function (r) { rows[r.dataset.day] = r; });

  function chosenDays() {
    return Array.prototype.filter.call(dayBoxes, function (b) { return b.checked; }).map(function (b) { return b.value; });
  }

  function applyShared() {
    chosenDays().forEach(function (d) {
      var row = rows[d];
      if (!row) return;
      row.querySelector('input[name$="[start]"]').value = sharedStart.value;
      row.querySelector('input[name$="[end]"]').value = sharedEnd.value;
    });
  }

  function render() {
    var days = chosenDays();
    var any = days.length > 0;
    body.hidden = !any;
    empty.hidden = any;

    Object.keys(rows).forEach(function (d) {
      var chosen = days.indexOf(d) !== -1;
      // Rows stay in the DOM but unnamed inputs are not submitted, so an
      // unchecked day cannot leave a stray time behind.
      rows[d].hidden = !chosen || same.checked;
      rows[d].querySelectorAll('input[type=time]').forEach(function (i) { i.disabled = !chosen; });
    });

    shared.hidden = !same.checked;
    if (same.checked) applyShared();
  }

  dayBoxes.forEach(function (b) { b.addEventListener('change', render); });
  same.addEventListener('change', render);
  [sharedStart, sharedEnd].forEach(function (i) { i.addEventListener('input', applyShared); });
  render();
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
