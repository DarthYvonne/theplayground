@extends('layouts.app')
@section('content')

<div class="view-header">
  <h1>
    <a href="{{ route('admin.courses.index') }}" style="color:inherit;"><i class="fa-solid fa-arrow-left" style="font-size:16px;margin-right:8px;"></i></a>
    {{ $course->exists ? 'Rediger hold' : 'Nyt hold' }}
  </h1>
  @include('partials.header-actions')
</div>

<div style="max-width: 720px;">
  <form method="POST" action="{{ $course->exists ? route('admin.courses.update', $course) : route('admin.courses.store') }}" enctype="multipart/form-data" class="card card-pad">
    @csrf
    <div class="form-row">
      <label for="title">Titel</label>
      <input id="title" type="text" name="title" value="{{ old('title', $course->title) }}" required>
    </div>
    <div class="form-row">
      <label for="description">Beskrivelse</label>
      <textarea id="description" name="description" rows="6" required>{{ old('description', $course->description) }}</textarea>
    </div>
    @php
      $existingTrainerIds = $course->exists ? $course->trainers->pluck('id')->all() : [];
      $oldTrainerIds = old('trainer_ids', $existingTrainerIds);
      $oldTrainerIds = array_map('intval', is_array($oldTrainerIds) ? $oldTrainerIds : []);
      $trainersPayload = $trainers->map(fn ($t) => [
        'id' => $t->id,
        'name' => $t->name,
        'role' => ['owner' => 'Ejer', 'trainer' => 'Træner'][$t->role] ?? $t->role,
        'picture_url' => $t->pictureUrl(),
        'initials' => $t->initials(),
      ])->values();
    @endphp
    <div class="form-row">
      <label>Trænere</label>
      <button type="button" class="find-link" id="openTrainerPicker"><i class="fa-solid fa-magnifying-glass"></i> Find trænere</button>
      <div class="trainer-chips chips-area" id="trainerChips"></div>
      @error('trainer_ids')<div class="hint" style="color:var(--danger);margin-top:6px;">{{ $message }}</div>@enderror
    </div>
    @php
      $priceKr = ($course->price_cents ?? 0) / 100;
      $priceKrDisplay = $priceKr == (int) $priceKr ? (string) (int) $priceKr : rtrim(rtrim(number_format($priceKr, 2, '.', ''), '0'), '.');
    @endphp
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;" class="grid-2">
      <div class="form-row">
        <label for="price_kr">Pris (kr/måned)</label>
        <input id="price_kr" type="number" name="price_kr" min="0" step="0.01" value="{{ old('price_kr', $priceKrDisplay) }}" required>
        <div class="hint">Hele kroner pr. måned. Brug 0 hvis holdet er gratis.</div>
      </div>
      <div class="form-row">
        <label for="max_participants">Maks. deltagere</label>
        <input id="max_participants" type="number" name="max_participants" min="1" value="{{ old('max_participants', $course->max_participants ?? 10) }}" required>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;" class="grid-2">
      <div class="form-row">
        <label for="start_time">Fra</label>
        <input id="start_time" type="time" name="start_time" value="{{ old('start_time', $course->start_time ? substr((string) $course->start_time, 0, 5) : '') }}">
      </div>
      <div class="form-row">
        <label for="end_time">Til</label>
        <input id="end_time" type="time" name="end_time" value="{{ old('end_time', $course->end_time ? substr((string) $course->end_time, 0, 5) : '') }}">
      </div>
    </div>

    <div class="form-row">
      <label>Ugedag(e)</label>
      @php $selectedDays = is_array(old('weekdays')) ? old('weekdays') : $course->weekdaysList(); @endphp
      <div class="weekday-row">
        @foreach (\App\Models\Course::WEEKDAYS as $code => $name)
          <label class="weekday-chip">
            <input type="checkbox" name="weekdays[]" value="{{ $code }}" {{ in_array($code, $selectedDays, true) ? 'checked' : '' }}>
            <span>{{ $name }}</span>
          </label>
        @endforeach
      </div>
    </div>
    <div class="form-row">
      <label for="image">Forsidebillede</label>
      <input id="image" type="file" name="image" accept="image/*">
      @if ($course->image_path)
        <div style="margin-top:8px;"><img src="{{ $course->imageUrl() }}" alt="" style="max-height:160px;border-radius:8px;"></div>
      @endif
      <div class="hint">Bruges hvis du ikke uploader en video.</div>
    </div>
    <div class="form-row">
      <label for="video">Forsidevideo (valgfri)</label>
      <input id="video" type="file" name="video" accept="video/mp4,video/quicktime,video/webm,video/x-m4v,video/x-matroska,video/avi">
      <div class="hint">MP4, MOV, AVI, WebM, M4V eller MKV. Maks 500 MB. Erstatter forsidebilledet på listesider.</div>
      @if ($course->hasVideo())
        <div style="margin-top:10px; display:flex; gap:12px; align-items:flex-start; flex-wrap:wrap;">
          @if ($course->videoThumbnailUrl())
            <img src="{{ $course->videoThumbnailUrl() }}" alt="" style="max-height:120px;border-radius:8px;">
          @endif
          <div style="font-size:13px;color:var(--muted);">
            Status:
            @switch($course->video_processing_status)
              @case('pending') Afventer behandling… @break
              @case('processing') Behandler… @break
              @case('completed') Klar (omkodet) @break
              @case('skipped') Klar @break
              @case('failed') Fejlede @break
              @default —
            @endswitch
            <div style="margin-top:6px;">
              <label class="switch" style="font-size:13px;">
                <input type="checkbox" name="remove_video" value="1">
                <span class="knob"></span>
                <span>Fjern videoen ved gem</span>
              </label>
            </div>
          </div>
        </div>
      @endif
    </div>
    <div class="form-row">
      <label class="switch">
        <input type="checkbox" name="is_active" value="1" {{ old('is_active', $course->is_active) ? 'checked' : '' }}>
        <span class="knob"></span>
        <span>Aktiv (vises på forsiden)</span>
      </label>
    </div>
    <div class="form-row">
      <label class="switch">
        <input type="checkbox" name="free_enrollment" value="1" {{ old('free_enrollment', $course->free_enrollment) ? 'checked' : '' }}>
        <span class="knob"></span>
        <span>Gratis tilmelding (spring Stripe over &mdash; mest til test)</span>
      </label>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;">
      <button class="btn btn-primary" type="submit">{{ $course->exists ? 'Gem ændringer' : 'Opret hold' }}</button>
      @if ($course->exists)
        <a href="{{ route('courses.show', $course) }}" class="btn btn-secondary"><i class="fa-regular fa-eye"></i> Vis</a>
        <form method="POST" action="{{ route('admin.courses.destroy', $course) }}" onsubmit="return confirm('Slet holdet og alle relaterede tilmeldinger?');">
          @csrf
          <button class="btn btn-danger" type="submit"><i class="fa-solid fa-trash"></i> Slet</button>
        </form>
      @endif
    </div>
  </form>
</div>

@push('styles')
<style>
  @media (max-width: 600px) { .grid-2 { grid-template-columns: 1fr !important; } }
  .weekday-row { display: flex; flex-wrap: wrap; gap: 6px; }
  .weekday-chip { display: inline-flex; align-items: center; gap: 0; cursor: pointer; user-select: none; font-weight: 500; }
  .weekday-chip input { position: absolute; opacity: 0; pointer-events: none; }
  .weekday-chip span { padding: 8px 14px; border-radius: 999px; border: 1px solid var(--border); background: #fff; font-size: 13px; transition: background 0.1s, border-color 0.1s, color 0.1s; }
  .weekday-chip:hover span { background: var(--hover); }
  .weekday-chip input:checked + span { background: var(--accent); border-color: var(--accent); color: #fff; }

  .find-link { margin-left: 4px; font-size: 13px; color: var(--accent); font-weight: 600; background: none; border: none; cursor: pointer; padding: 0; text-decoration: underline; }
  .find-link:hover { text-decoration-thickness: 2px; }
  .chips-area { display: flex; flex-wrap: wrap; gap: 6px; min-height: 28px; margin-top: 10px; }
  .chips-area:empty::before { content: 'Ingen valgt.'; color: var(--muted); font-size: 13px; font-style: italic; }
  .pill { display: inline-flex; align-items: center; gap: 6px; background: var(--accent-soft); color: var(--accent); padding: 4px 8px 4px 4px; border-radius: 999px; font-weight: 600; font-size: 13px; }
  .pill button { background: none; border: none; color: inherit; cursor: pointer; font-size: 14px; padding: 0 2px; line-height: 1; }
  .pill .av { width: 22px; height: 22px; font-size: 10px; }

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
    if (t.picture_url) return '<img src="' + escapeHtml(t.picture_url) + '" style="width:22px;height:22px;border-radius:50%;object-fit:cover;">';
    return '<div class="av sm" style="width:22px;height:22px;font-size:10px;">' + escapeHtml(t.initials || '?') + '</div>';
  }

  function renderChips() {
    var html = '';
    Object.keys(selected).forEach(function (id) {
      var t = selected[id];
      html += '<span class="pill">' + avatarHtml(t) + '<span>' + escapeHtml(t.name) +
        '</span><button type="button" data-remove="' + id + '" aria-label="Fjern">×</button>' +
        '<input type="hidden" name="trainer_ids[]" value="' + id + '"></span>';
    });
    chips.innerHTML = html;
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
        '<div class="meta"><div class="nm">' + escapeHtml(t.name) + '</div><div class="sub">' + escapeHtml(t.role) + '</div></div>' +
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
