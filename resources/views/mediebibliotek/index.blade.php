@extends('layouts.app')
@section('content')

@push('styles')
<style>
  .media-shell { max-width: 720px; }

  .media-search { position: relative; margin-bottom: 16px; }
  .media-search i.ico { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--muted); font-size: 13px; }
  .media-search input { width: 100%; padding: 9px 12px 9px 32px; border: 1px solid var(--border); border-radius: 999px; font: inherit; background: #fff; }
  .media-search input:focus { outline: none; border-color: var(--accent); }
  .media-search .clear { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); color: var(--muted); font-size: 13px; padding: 4px; cursor: pointer; display: none; background: none; border: none; }

  .media-upload-btn { display: flex; width: 100%; align-items: center; justify-content: center; gap: 8px; padding: 13px 16px; font-size: 15px; font-weight: 700; margin-bottom: 16px; }

  .media-bar { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; min-height: 22px; }
  .media-nav { display: flex; flex-wrap: wrap; align-items: center; font-size: 14px; flex: 1; min-width: 0; }
  .media-nav a { color: var(--muted); font-weight: 600; padding: 2px 0; }
  .media-nav a:hover { color: var(--accent); }
  .media-nav a::before { content: "|"; color: var(--border); margin: 0 10px; font-weight: 400; }
  .media-nav a.lead::before { content: none; }
  .media-nav a .cnt { color: var(--text); font-weight: 700; }

  .media-section { margin-bottom: 26px; scroll-margin-top: 16px; }
  @media (max-width: 900px) {
    /* Clear the fixed 56px mobile topbar when scrolling to a section */
    .media-section { scroll-margin-top: 72px; }
  }
  .media-section > h2 { font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.4px; color: var(--muted); margin-bottom: 10px; }

  .media-grid { display: grid; grid-template-columns: 1fr; gap: 14px; }
  .media-card { background: #fff; border: 1px solid var(--border); border-radius: 12px; overflow: hidden; box-shadow: 0 1px 2px rgba(0,0,0,0.06); }
  .media-card .body { padding: 12px 14px; }
  .media-card .title { font-weight: 700; font-size: 15px; display: flex; align-items: flex-start; gap: 10px; }
  .media-card .title .txt { flex: 1; }
  .media-card .desc { color: var(--muted); font-size: 13px; margin-top: 4px; white-space: pre-wrap; }
  .media-card .meta { color: var(--muted); font-size: 11px; margin-top: 8px; }
  .media-card video, .media-card img.thumb { display: block; width: 100%; background: #000; max-height: 460px; object-fit: contain; }
  .media-card img.thumb { background: #f0f2f5; cursor: zoom-in; }
  .media-card .audio-wrap { padding: 12px 14px 0; }
  .media-card audio { width: 100%; }
  .media-card .state { padding: 28px 14px; text-align: center; color: var(--muted); font-size: 13px; background: #f0f2f5; }
  .media-card .state.failed { color: var(--danger); }
  .media-card .del { background: none; border: none; cursor: pointer; color: var(--muted); padding: 4px 8px; border-radius: 6px; font-size: 14px; }
  .media-card .del:hover { background: #fdeaea; color: var(--danger); }

  .media-empty { background: #fff; border-radius: 12px; padding: 40px 20px; text-align: center; color: var(--muted); box-shadow: 0 1px 2px rgba(0,0,0,0.06); }
  .media-empty h3 { color: var(--text); margin-bottom: 6px; }

  /* Upload modal */
  /* max-width:none beats the layout's `.main > * { max-width: 720px }`, which
     would otherwise squeeze this fixed overlay to a 720px strip. */
  .media-modal-backdrop { position: fixed; inset: 0; width: 100%; max-width: none; background: rgba(0,0,0,0.45); z-index: 9998; display: none; align-items: center; justify-content: center; padding: 20px; }
  .media-modal-backdrop.open { display: flex; }
  .media-modal { background: #fff; border-radius: 12px; width: 100%; max-width: 480px; box-shadow: 0 10px 32px rgba(0,0,0,0.22); overflow: hidden; max-height: calc(100vh - 40px); display: flex; flex-direction: column; }
  .media-modal .head { padding: 16px 20px; border-bottom: 1px solid #f0f2f5; display: flex; align-items: center; gap: 10px; flex: 0 0 auto; }
  .media-modal .head .title { font-weight: 700; flex: 1; font-size: 16px; }
  .media-modal .head .close { background: none; border: none; cursor: pointer; padding: 6px 10px; border-radius: 6px; color: var(--muted); font-size: 16px; }
  .media-modal .head .close:hover { background: var(--hover); color: var(--text); }
  .media-modal form { display: flex; flex-direction: column; min-height: 0; }
  .media-modal .mbody { padding: 16px 20px; display: flex; flex-direction: column; gap: 12px; overflow-y: auto; }
  .media-modal label { font-size: 12px; font-weight: 600; color: var(--muted); display: block; margin-bottom: 4px; }
  .media-modal input[type=text], .media-modal textarea, .media-modal input[type=file] { width: 100%; padding: 9px 11px; border: 1px solid var(--border); border-radius: 8px; font: inherit; background: #fff; }
  .media-modal input[type=text]:focus, .media-modal textarea:focus { outline: none; border-color: var(--accent); }
  .media-modal textarea { resize: vertical; min-height: 64px; }
  .media-modal .req { color: var(--danger); }
  .media-modal .foot { padding: 12px 20px 16px; border-top: 1px solid #f0f2f5; display: flex; flex-direction: column; gap: 10px; flex: 0 0 auto; }
  .media-modal .foot .hint { color: var(--muted); font-size: 12px; text-align: center; }
  .media-modal .foot .btn { width: 100%; justify-content: center; padding: 13px 16px; font-size: 15px; font-weight: 700; display: inline-flex; align-items: center; gap: 8px; }

  /* Mobile: bottom sheet */
  @media (max-width: 600px) {
    .media-modal-backdrop { padding: 0; align-items: flex-end; }
    .media-modal { max-width: none; border-radius: 16px 16px 0 0; max-height: 92dvh; }
    .media-modal .mbody { padding: 14px 16px; }
    .media-modal .head, .media-modal .foot { padding-left: 16px; padding-right: 16px; }
    /* 16px font prevents iOS from zooming the page when focusing inputs */
    .media-modal input[type=text], .media-modal textarea { font-size: 16px; }
    .media-modal .foot { padding-bottom: calc(16px + env(safe-area-inset-bottom)); }
  }

  /* Image lightbox */
  .media-lightbox { position: fixed; inset: 0; width: 100%; max-width: none; background: rgba(0,0,0,0.85); z-index: 9998; display: none; align-items: center; justify-content: center; padding: 24px; }
  .media-lightbox.open { display: flex; }
  .media-lightbox img { max-width: 100%; max-height: 100%; border-radius: 6px; }
  .media-lightbox .close { position: absolute; top: 18px; right: 22px; color: #fff; font-size: 26px; background: none; border: none; cursor: pointer; }
</style>
@endpush

@php
  $labels = ['video' => 'Video', 'audio' => 'Lyd', 'image' => 'Billeder'];
  $hasAny = collect($groups)->flatten()->isNotEmpty();
@endphp

<div class="view-header">
  <h1><i class="fa-solid fa-photo-film" style="color:var(--accent);margin-right:8px;"></i>Mediebibliotek</h1>
  @include('partials.header-actions')
</div>

<div class="media-shell">
  <div class="media-search">
    <i class="fa-solid fa-magnifying-glass ico"></i>
    <input type="text" id="mediaSearch" placeholder="Søg i mediebiblioteket…" autocomplete="off" aria-label="Søg">
    <button type="button" class="clear" id="mediaSearchClear" aria-label="Ryd søgning"><i class="fa-solid fa-xmark"></i></button>
  </div>

  @if ($isOwner)
    <button type="button" class="btn btn-primary media-upload-btn" id="mediaUploadOpen">
      <i class="fa-solid fa-plus"></i> Upload
    </button>
  @endif

  @if ($hasAny)
    <div class="media-bar">
      <nav class="media-nav" id="mediaNav">
        @php $first = true; @endphp
        @foreach ($labels as $type => $label)
          @if ($groups[$type]->isNotEmpty())
            <a href="#sec-{{ $type }}" data-type="{{ $type }}" class="{{ $first ? 'lead' : '' }}">{{ $label }} (<span class="cnt">{{ $groups[$type]->count() }}</span>)</a>
            @php $first = false; @endphp
          @endif
        @endforeach
      </nav>
    </div>
  @endif

  <div id="mediaNoResults" class="media-empty" style="display:none;">
    <h3>Ingen resultater</h3>
    <p>Prøv en anden søgning.</p>
  </div>

  @if (!$hasAny)
    <div class="media-empty">
      <h3>Mediebiblioteket er tomt</h3>
      <p>{{ $isOwner ? 'Upload det første medie med knappen ovenfor.' : 'Der er ikke uploadet noget endnu.' }}</p>
    </div>
  @else
    @foreach ($labels as $type => $label)
      @continue($groups[$type]->isEmpty())
      <section class="media-section" id="sec-{{ $type }}" data-type="{{ $type }}">
        <h2>{{ $label }}</h2>
        <div class="media-grid">
          @foreach ($groups[$type] as $item)
            <div class="media-card" data-search="{{ \Illuminate\Support\Str::lower(trim($item->title . ' ' . $item->description)) }}">
              @if ($item->type === 'video')
                @if ($item->isProcessing())
                  <div class="state"><i class="fa-solid fa-spinner fa-spin"></i> Videoen behandles…</div>
                @elseif ($item->hasFailed())
                  <div class="state failed"><i class="fa-solid fa-triangle-exclamation"></i> Videoen kunne ikke behandles.</div>
                @elseif ($item->url())
                  <video controls preload="metadata" @if ($item->thumbnailUrl()) poster="{{ $item->thumbnailUrl() }}" @endif>
                    <source src="{{ $item->url() }}" type="video/mp4">
                  </video>
                @endif
              @elseif ($item->type === 'audio')
                <div class="audio-wrap">
                  <audio controls preload="none" src="{{ $item->url() }}"></audio>
                </div>
              @elseif ($item->type === 'image')
                <img class="thumb" src="{{ $item->url() }}" alt="{{ $item->title }}" loading="lazy" data-full="{{ $item->url() }}">
              @endif

              <div class="body">
                <div class="title">
                  <span class="txt">{{ $item->title }}</span>
                  @if ($isOwner)
                    <form method="POST" action="{{ route('media.destroy', $item) }}" onsubmit="return confirm('Slet dette medie?');">
                      @csrf
                      <button type="submit" class="del" title="Slet" aria-label="Slet"><i class="fa-solid fa-trash"></i></button>
                    </form>
                  @endif
                </div>
                @if ($item->description)
                  <div class="desc">{{ $item->description }}</div>
                @endif
                <div class="meta">{{ $item->created_at->format('d.m.Y') }}</div>
              </div>
            </div>
          @endforeach
        </div>
      </section>
    @endforeach
  @endif
</div>

@if ($isOwner)
  <div class="media-modal-backdrop {{ $errors->any() ? 'open' : '' }}" id="uploadBackdrop" role="dialog" aria-modal="true" aria-labelledby="uploadTitle">
    <div class="media-modal">
      <div class="head">
        <div class="title" id="uploadTitle">Upload medie</div>
        <button type="button" class="close" id="uploadClose" aria-label="Luk"><i class="fa-solid fa-xmark"></i></button>
      </div>
      <form method="POST" action="{{ route('media.store') }}" enctype="multipart/form-data" id="uploadForm">
        @csrf
        <div class="mbody">
          <div>
            <label for="uploadTitleInput">Titel <span class="req">*</span></label>
            <input type="text" name="title" id="uploadTitleInput" value="{{ old('title') }}" maxlength="255" required>
          </div>
          <div>
            <label for="uploadDesc">Beskrivelse</label>
            <textarea name="description" id="uploadDesc" maxlength="2000" placeholder="Valgfri">{{ old('description') }}</textarea>
          </div>
          <div>
            <label for="uploadFile">Fil <span class="req">*</span></label>
            <input type="file" name="file" id="uploadFile" accept="video/*,audio/*,image/*" required>
          </div>
        </div>
        <div class="foot">
          <span class="hint">Video, lyd eller billede — typen registreres automatisk.</span>
          <button type="submit" class="btn btn-primary" id="uploadSubmit">
            <i class="fa-solid fa-upload"></i> Upload
          </button>
        </div>
      </form>
    </div>
  </div>
@endif

<div class="media-lightbox" id="mediaLightbox">
  <button type="button" class="close" id="mediaLightboxClose" aria-label="Luk"><i class="fa-solid fa-xmark"></i></button>
  <img src="" alt="" id="mediaLightboxImg">
</div>

@push('scripts')
<script>
(function () {
  // ---- Live search across all groups ----
  var search = document.getElementById('mediaSearch');
  var clearBtn = document.getElementById('mediaSearchClear');
  var noResults = document.getElementById('mediaNoResults');
  var sections = Array.prototype.slice.call(document.querySelectorAll('.media-section'));
  var navLinks = {};
  document.querySelectorAll('#mediaNav a[data-type]').forEach(function (a) {
    navLinks[a.dataset.type] = { el: a, cnt: a.querySelector('.cnt') };
    // Scroll in-page instead of navigating — no reload, no URL hash, no jump.
    a.addEventListener('click', function (e) {
      e.preventDefault();
      var sec = document.getElementById('sec-' + a.dataset.type);
      if (sec) sec.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  });

  function apply() {
    var q = (search.value || '').toLowerCase().trim();
    clearBtn.style.display = q ? 'block' : 'none';

    var totalVisible = 0;
    var firstVisibleLink = null;

    sections.forEach(function (sec) {
      var visible = 0;
      sec.querySelectorAll('.media-card').forEach(function (card) {
        var match = q === '' || (card.getAttribute('data-search') || '').indexOf(q) !== -1;
        card.style.display = match ? '' : 'none';
        if (match) visible++;
      });
      sec.style.display = visible ? '' : 'none';
      totalVisible += visible;

      var link = navLinks[sec.dataset.type];
      if (link) {
        if (link.cnt) link.cnt.textContent = visible;
        link.el.style.display = visible ? '' : 'none';
        link.el.classList.remove('lead');
        if (visible && !firstVisibleLink) firstVisibleLink = link.el;
      }
    });

    if (firstVisibleLink) firstVisibleLink.classList.add('lead');
    if (noResults) noResults.style.display = (q !== '' && totalVisible === 0) ? '' : 'none';
  }

  if (search) {
    search.addEventListener('input', apply);
    clearBtn.addEventListener('click', function () { search.value = ''; apply(); search.focus(); });
    apply();
  }

  // ---- Upload modal ----
  var upBackdrop = document.getElementById('uploadBackdrop');
  if (upBackdrop) {
    var upOpen = document.getElementById('mediaUploadOpen');
    var upClose = document.getElementById('uploadClose');
    var upForm = document.getElementById('uploadForm');
    var upSubmit = document.getElementById('uploadSubmit');

    function openUpload() {
      upBackdrop.classList.add('open');
      var t = document.getElementById('uploadTitleInput');
      if (t) setTimeout(function () { t.focus(); }, 50);
    }
    function closeUpload() { upBackdrop.classList.remove('open'); }

    if (upOpen) upOpen.addEventListener('click', openUpload);
    upClose.addEventListener('click', closeUpload);
    upBackdrop.addEventListener('click', function (e) { if (e.target === upBackdrop) closeUpload(); });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && upBackdrop.classList.contains('open')) closeUpload();
    });
    // Large videos take a while — show progress state and prevent double submits.
    upForm.addEventListener('submit', function () {
      upSubmit.disabled = true;
      upSubmit.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Uploader…';
    });
  }

  // ---- Image lightbox ----
  var box = document.getElementById('mediaLightbox');
  var img = document.getElementById('mediaLightboxImg');
  var closeBtn = document.getElementById('mediaLightboxClose');
  function closeBox() { box.classList.remove('open'); img.src = ''; }
  document.querySelectorAll('.media-card img.thumb[data-full]').forEach(function (el) {
    el.addEventListener('click', function () { img.src = el.dataset.full; box.classList.add('open'); });
  });
  closeBtn.addEventListener('click', closeBox);
  box.addEventListener('click', function (e) { if (e.target === box) closeBox(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && box.classList.contains('open')) closeBox(); });
})();
</script>
@endpush

@endsection
