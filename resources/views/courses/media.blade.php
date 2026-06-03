@extends('layouts.app')
@section('content')

@push('styles')
<style>
  .cmedia-shell { max-width: 720px; }

  .cmedia-actions { display: flex; gap: 10px; margin-bottom: 16px; }
  .cmedia-actions .btn { flex: 1; justify-content: center; display: inline-flex; align-items: center; gap: 8px; padding: 12px 16px; font-size: 15px; font-weight: 700; }

  /* Same compact card grid as the Mediebibliotek */
  .cmedia-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 12px; }
  .cmedia-card { background: #fff; border: 1px solid var(--border); border-radius: 12px; overflow: hidden; box-shadow: 0 1px 2px rgba(0,0,0,0.06); display: flex; flex-direction: column; }
  .cmedia-card video { display: block; width: 100%; height: 130px; background: #000; object-fit: contain; }
  .cmedia-card img.cm-img { display: block; width: 100%; height: 130px; object-fit: cover; background: #f0f2f5; cursor: zoom-in; }
  .cmedia-card .cm-audio { padding: 10px 12px 0; }
  .cmedia-card .cm-state { height: 130px; display: flex; align-items: center; justify-content: center; gap: 8px; text-align: center; color: var(--muted); font-size: 12px; background: #f0f2f5; padding: 0 12px; }
  .cmedia-card .cm-state.failed { color: var(--danger); }
  .cmedia-card .cm-body { padding: 10px 12px; display: flex; flex-direction: column; flex: 1; }
  .cmedia-card .cm-comment { font-size: 13px; line-height: 1.4; white-space: pre-wrap; word-break: break-word; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
  .cmedia-card .cm-meta { color: var(--muted); font-size: 11px; display: flex; align-items: center; gap: 8px; margin-top: auto; padding-top: 6px; }
  .cmedia-card .cm-meta .del { margin-left: auto; background: none; border: none; cursor: pointer; color: var(--muted); padding: 2px 6px; border-radius: 6px; font-size: 13px; }
  .cmedia-card .cm-meta .del:hover { background: #fdeaea; color: var(--danger); }
  @media (max-width: 480px) {
    .cmedia-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
    .cmedia-card video, .cmedia-card img.cm-img, .cmedia-card .cm-state { height: 110px; }
  }

  .cmedia-empty { background: #fff; border-radius: 12px; padding: 40px 20px; text-align: center; color: var(--muted); box-shadow: 0 1px 2px rgba(0,0,0,0.06); }
  .cmedia-empty h3 { color: var(--text); margin-bottom: 6px; }

  /* Modals (shared shell) — width:100%/max-width:none beats `.main > *`'s 720px cap */
  .cm-backdrop { position: fixed; inset: 0; width: 100%; max-width: none; margin: 0; background: rgba(0,0,0,0.45); z-index: 9998; display: none; align-items: center; justify-content: center; padding: 20px; }
  .cm-backdrop.open { display: flex; }
  .cm-modal { background: #fff; border-radius: 12px; width: 100%; max-width: 480px; max-height: 80vh; display: flex; flex-direction: column; box-shadow: 0 10px 32px rgba(0,0,0,0.22); overflow: hidden; }
  .cm-modal .head { padding: 14px 18px; border-bottom: 1px solid #f0f2f5; display: flex; align-items: center; gap: 10px; flex: 0 0 auto; }
  .cm-modal .head .title { font-weight: 700; flex: 1; }
  .cm-modal .head .title i { color: var(--accent); margin-right: 6px; }
  .cm-modal .head .close { background: none; border: none; cursor: pointer; padding: 6px 10px; border-radius: 6px; color: var(--muted); font-size: 16px; }
  .cm-modal .head .close:hover { background: var(--hover); color: var(--text); }
  .cm-modal .mbody { padding: 16px 18px; display: flex; flex-direction: column; gap: 12px; overflow-y: auto; }
  .cm-modal label { font-size: 12px; font-weight: 600; color: var(--muted); display: block; margin-bottom: 4px; }
  .cm-modal input[type=file], .cm-modal textarea { width: 100%; padding: 9px 11px; border: 1px solid var(--border); border-radius: 8px; font: inherit; background: #fff; }
  .cm-modal textarea { resize: vertical; min-height: 70px; }
  .cm-modal textarea:focus { outline: none; border-color: var(--accent); }
  .cm-modal .foot { padding: 12px 18px 16px; border-top: 1px solid #f0f2f5; display: flex; flex-direction: column; gap: 10px; flex: 0 0 auto; }
  .cm-modal .foot .btn { width: 100%; justify-content: center; display: inline-flex; align-items: center; gap: 8px; padding: 13px 16px; font-size: 15px; font-weight: 700; }

  /* Selected media summary in the attach modal */
  .lib-thumb { width: 56px; height: 42px; border-radius: 8px; overflow: hidden; flex: 0 0 auto; background: #f0f2f5; display: flex; align-items: center; justify-content: center; }
  .lib-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
  .lib-thumb .ph { color: var(--muted); font-size: 18px; }
  .lib-thumb .ph.audio, .lib-thumb .ph.playlist { color: var(--accent); }
  .lib-meta { min-width: 0; flex: 1; }
  .lib-meta .ttl { display: block; font-weight: 600; font-size: 14px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .lib-meta .sub { display: block; color: var(--muted); font-size: 12px; }
  .lib-selected { display: flex; align-items: center; gap: 12px; background: var(--accent-soft); border-radius: 10px; padding: 10px 12px; }
  .lib-selected .back { margin-left: auto; background: none; border: none; cursor: pointer; color: var(--accent); font-size: 13px; font-weight: 600; padding: 4px 8px; }

  /* Shared playlist entries span the full grid width */
  .cmedia-card.span-full { grid-column: 1 / -1; }
  .cmedia-card .tp-playlist { border: none; border-radius: 0; border-bottom: 1px solid #f0f2f5; }

  @media (max-width: 600px) {
    .cm-backdrop { padding: 0; align-items: flex-end; }
    .cm-modal { max-width: none; border-radius: 16px 16px 0 0; max-height: 88dvh; }
    .cm-modal textarea, .cm-modal .lib-search input { font-size: 16px; }
    .cm-modal .foot { padding-bottom: calc(16px + env(safe-area-inset-bottom)); }
  }

  /* Image lightbox */
  .cm-lightbox { position: fixed; inset: 0; width: 100%; max-width: none; background: rgba(0,0,0,0.85); z-index: 9999; display: none; align-items: center; justify-content: center; padding: 24px; }
  .cm-lightbox.open { display: flex; }
  .cm-lightbox img { max-width: 100%; max-height: 100%; border-radius: 6px; }
  .cm-lightbox .close { position: absolute; top: 18px; right: 22px; color: #fff; font-size: 26px; background: none; border: none; cursor: pointer; }
</style>
@endpush

<div class="view-header">
  <h1>
    <a href="{{ route('courses.show', $course) }}" style="color:inherit;"><i class="fa-solid fa-arrow-left" style="font-size:16px;margin-right:8px;"></i></a>
    {{ $course->title }}
  </h1>
  @include('partials.header-actions')
</div>

@include('partials.course-tabs')
@include('partials.audio-player')
@if ($canManage)
  @include('partials.media-picker')
@endif

<div class="cmedia-shell">
  @if ($canManage)
    <div class="cmedia-actions">
      <button type="button" class="btn btn-primary" id="cmUploadOpen"><i class="fa-solid fa-upload"></i> Upload</button>
      <button type="button" class="btn btn-secondary" id="cmLibOpen"><i class="fa-solid fa-photo-film"></i> Fra mediebiblioteket</button>
    </div>
  @endif

  @if ($items->isEmpty())
    <div class="cmedia-empty">
      <h3>Ingen medier endnu</h3>
      <p>Upload eller tilføj fra mediebiblioteket med knapperne ovenfor.</p>
    </div>
  @else
    <div class="cmedia-grid">
      @foreach ($items as $item)
        @continue($item->type === 'playlist' ? !$item->playlist : (!$item->url() && !$item->isProcessing() && !$item->hasFailed()))
        <div class="cmedia-card {{ $item->type === 'playlist' ? 'span-full' : '' }}">
          @if ($item->type === 'playlist')
            <div class="tp-playlist" data-playlist="{{ json_encode($item->playlist->toPayload()) }}"></div>
          @elseif ($item->type === 'video')
            @if ($item->isProcessing())
              <div class="cm-state"><i class="fa-solid fa-spinner fa-spin"></i> Videoen behandles…</div>
            @elseif ($item->hasFailed())
              <div class="cm-state failed"><i class="fa-solid fa-triangle-exclamation"></i> Videoen kunne ikke behandles.</div>
            @elseif ($item->url())
              <video controls preload="metadata" playsinline src="{{ $item->url() }}"
                @if ($item->thumbnailUrl()) poster="{{ $item->thumbnailUrl() }}" @endif></video>
            @endif
          @elseif ($item->type === 'image')
            <img class="cm-img" src="{{ $item->url() }}" alt="" loading="lazy" data-full="{{ $item->url() }}">
          @elseif ($item->type === 'audio')
            <div class="cm-audio">
              <div class="tp-audio sm" data-src="{{ $item->url() }}"></div>
            </div>
          @endif

          <div class="cm-body">
            @if (trim((string) $item->comment) !== '')
              <div class="cm-comment">{{ $item->comment }}</div>
            @endif
            <div class="cm-meta">
              <span>{{ $item->user?->name ?? 'Ukendt' }} · {{ $item->created_at->format('d.m.Y') }}</span>
              @if ($canManage)
                <form method="POST" action="{{ route('courses.media.destroy', [$course, $item]) }}" class="del-form" onsubmit="return confirm('Slet dette medie fra holdet?');" style="margin-left:auto;">
                  @csrf
                  <button type="submit" class="del" title="Slet" aria-label="Slet"><i class="fa-solid fa-trash"></i></button>
                </form>
              @endif
            </div>
          </div>
        </div>
      @endforeach
    </div>
  @endif
</div>

@if ($canManage)
  {{-- Upload modal --}}
  <div class="cm-backdrop {{ $errors->any() ? 'open' : '' }}" id="cmUploadBackdrop" role="dialog" aria-modal="true">
    <div class="cm-modal">
      <div class="head">
        <div class="title"><i class="fa-solid fa-upload"></i> Upload medie</div>
        <button type="button" class="close" data-close="cmUploadBackdrop" aria-label="Luk"><i class="fa-solid fa-xmark"></i></button>
      </div>
      <form method="POST" action="{{ route('courses.media.store', $course) }}" enctype="multipart/form-data" id="cmUploadForm">
        @csrf
        <div class="mbody">
          <div>
            <label>Fil</label>
            <input type="file" name="file" accept="video/*,audio/*,image/*" required>
          </div>
          <div>
            <label for="cmUploadComment">Kommentar</label>
            <textarea name="comment" id="cmUploadComment" maxlength="2000" placeholder="Hvad vil du fortælle om dette?">{{ old('comment') }}</textarea>
          </div>
        </div>
        <div class="foot">
          <button type="submit" class="btn btn-primary" id="cmUploadSubmit"><i class="fa-solid fa-upload"></i> Upload</button>
        </div>
      </form>
    </div>
  </div>

  {{-- Attach-from-library modal: the pick itself happens in the shared media picker --}}
  <div class="cm-backdrop" id="cmLibBackdrop" role="dialog" aria-modal="true">
    <div class="cm-modal">
      <div class="head">
        <div class="title"><i class="fa-solid fa-photo-film"></i> Tilføj til holdet</div>
        <button type="button" class="close" data-close="cmLibBackdrop" aria-label="Luk"><i class="fa-solid fa-xmark"></i></button>
      </div>
      <form method="POST" action="{{ route('courses.media.store', $course) }}" id="cmLibForm">
        @csrf
        <input type="hidden" name="media_item_id" id="cmLibItemId">
        <input type="hidden" name="playlist_id" id="cmLibPlaylistId">
        <div class="mbody">
          <div class="lib-selected" id="cmLibSelected"></div>
          <div>
            <label for="cmLibComment">Kommentar</label>
            <textarea name="comment" id="cmLibComment" maxlength="2000" placeholder="Hvad vil du fortælle om dette?"></textarea>
          </div>
        </div>
        <div class="foot">
          <button type="submit" class="btn btn-primary" id="cmLibSubmit"><i class="fa-solid fa-plus"></i> Tilføj til holdet</button>
        </div>
      </form>
    </div>
  </div>
@endif

<div class="cm-lightbox" id="cmLightbox">
  <button type="button" class="close" id="cmLightboxClose" aria-label="Luk"><i class="fa-solid fa-xmark"></i></button>
  <img src="" alt="" id="cmLightboxImg">
</div>

@push('scripts')
<script>
(function () {
  if (window.tpAudio) tpAudio.init();

  // ---- Generic modal open/close ----
  function openModal(id) { document.getElementById(id).classList.add('open'); }
  function closeModal(id) { document.getElementById(id).classList.remove('open'); }
  document.querySelectorAll('[data-close]').forEach(function (btn) {
    btn.addEventListener('click', function () { closeModal(btn.dataset.close); });
  });
  document.querySelectorAll('.cm-backdrop').forEach(function (bd) {
    bd.addEventListener('click', function (e) { if (e.target === bd) bd.classList.remove('open'); });
  });
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    document.querySelectorAll('.cm-backdrop.open').forEach(function (bd) { bd.classList.remove('open'); });
  });

  function escapeHtml(s) { return String(s ?? '').replace(/[&<>"']/g, function (m) { return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[m]; }); }

  // ---- Upload modal ----
  var upOpen = document.getElementById('cmUploadOpen');
  if (upOpen) {
    upOpen.addEventListener('click', function () { openModal('cmUploadBackdrop'); });
    var upForm = document.getElementById('cmUploadForm');
    var upSubmit = document.getElementById('cmUploadSubmit');
    upForm.addEventListener('submit', function () {
      upSubmit.disabled = true;
      upSubmit.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Uploader…';
    });
  }

  // ---- Library picker — shared modal, then a comment step ----
  var libOpenBtn = document.getElementById('cmLibOpen');
  if (libOpenBtn && window.mediaPicker) {
    var libForm = document.getElementById('cmLibForm');
    var libItemId = document.getElementById('cmLibItemId');
    var libPlaylistId = document.getElementById('cmLibPlaylistId');
    var libSelected = document.getElementById('cmLibSelected');
    var libComment = document.getElementById('cmLibComment');
    var libSubmit = document.getElementById('cmLibSubmit');
    var typeLabel = { video: 'Video', audio: 'Lyd', image: 'Billede' };

    function thumbFor(it) {
      if (it.type === 'image' && it.url) return '<img src="' + escapeHtml(it.url) + '" alt="">';
      if (it.type === 'video' && it.thumbnail_url) return '<img src="' + escapeHtml(it.thumbnail_url) + '" alt="">';
      if (it.type === 'video') return '<span class="ph"><i class="fa-solid fa-film"></i></span>';
      return '<span class="ph audio"><i class="fa-solid fa-music"></i></span>';
    }

    function handlePick(sel) {
      var thumb, ttl, sub;
      if (sel.kind === 'playlist') {
        libItemId.value = '';
        libPlaylistId.value = sel.playlist.id;
        thumb = sel.playlist.image_url ? '<img src="' + escapeHtml(sel.playlist.image_url) + '" alt="">' : '<span class="ph playlist"><i class="fa-solid fa-list-ul"></i></span>';
        ttl = sel.playlist.name;
        sub = 'Playliste · ' + sel.playlist.count + ' medier';
      } else {
        libItemId.value = sel.item.id;
        libPlaylistId.value = '';
        thumb = thumbFor(sel.item);
        ttl = sel.item.title;
        sub = typeLabel[sel.item.type] || '';
      }
      libComment.value = '';
      libSelected.innerHTML =
        '<span class="lib-thumb">' + thumb + '</span>' +
        '<span class="lib-meta"><span class="ttl">' + escapeHtml(ttl) + '</span><span class="sub">' + sub + '</span></span>' +
        '<button type="button" class="back" id="cmLibBack">Skift</button>';
      openModal('cmLibBackdrop');
      document.getElementById('cmLibBack').addEventListener('click', function () {
        closeModal('cmLibBackdrop');
        mediaPicker.open(handlePick);
      });
      libComment.focus();
    }

    libOpenBtn.addEventListener('click', function () { mediaPicker.open(handlePick); });

    libForm.addEventListener('submit', function () {
      libSubmit.disabled = true;
      libSubmit.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Tilføjer…';
    });
  }

  // ---- Image lightbox ----
  var box = document.getElementById('cmLightbox');
  var img = document.getElementById('cmLightboxImg');
  var closeBtn = document.getElementById('cmLightboxClose');
  function closeBox() { box.classList.remove('open'); img.src = ''; }
  document.querySelectorAll('img.cm-img[data-full]').forEach(function (el) {
    el.addEventListener('click', function () { img.src = el.dataset.full; box.classList.add('open'); });
  });
  closeBtn.addEventListener('click', closeBox);
  box.addEventListener('click', function (e) { if (e.target === box) closeBox(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && box.classList.contains('open')) closeBox(); });
})();
</script>
@endpush

@endsection
