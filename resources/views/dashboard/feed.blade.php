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
  .composer-actions { display: flex; align-items: center; justify-content: flex-end; margin-top: 10px; gap: 10px; }
  .composer-actions .btn { padding: 9px 22px; }
  .composer-error { color: var(--danger); font-size: 12px; margin-top: 6px; }
  .composer-attach-btn { background: none; border: none; width: 44px; height: 44px; border-radius: 50%; color: var(--muted); cursor: pointer; display: inline-flex; align-items: center; justify-content: center; font-size: 24px; }
  .composer-attach-btn + .composer-attach-btn { margin-left: -10px; }
  .composer-attach-btn:hover { background: var(--hover); color: var(--accent); }
  .composer-attach-btn:disabled { cursor: default; opacity: 0.5; }
  .composer-upload-status { color: var(--muted); font-size: 13px; display: inline-flex; align-items: center; gap: 6px; }
  .composer-upload-status .fa-spinner { color: var(--accent); }
  .composer-preview { margin-top: 10px; display: inline-flex; position: relative; max-width: 100%; }
  .composer-preview img, .composer-preview video { max-height: 200px; max-width: 100%; border-radius: 10px; display: block; background: #000; }
  .composer-preview-remove { position: absolute; top: 4px; right: 4px; width: 24px; height: 24px; border-radius: 50%; background: rgba(0,0,0,0.6); color: #fff; border: none; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; font-size: 12px; z-index: 2; }
  .composer-preview-remove:hover { background: rgba(0,0,0,0.8); }
  .composer-upload-progress { display: inline-flex; align-items: center; gap: 8px; color: var(--muted); font-size: 13px; }
  .composer-upload-progress .bar { width: 120px; height: 6px; background: #e4e6eb; border-radius: 3px; overflow: hidden; }
  .composer-upload-progress .bar > span { display: block; height: 100%; background: var(--accent); width: 0; transition: width 0.15s linear; }
  .composer-media-chip { display: none; align-items: center; gap: 10px; background: var(--accent-soft); color: var(--accent); border-radius: 10px; padding: 12px 42px 12px 14px; font-weight: 600; font-size: 13px; max-width: 100%; }
  .composer-media-chip i { font-size: 16px; flex: 0 0 auto; }
  .composer-media-chip .t { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .feed-image { margin-top: 10px; }
  .feed-image img { max-width: 100%; max-height: 520px; border-radius: 10px; display: block; cursor: zoom-in; }
  .feed-audio { margin-top: 10px; }
  .feed-video { margin-top: 10px; position: relative; }
  .feed-video video { width: 100%; max-height: 520px; border-radius: 10px; display: block; background: #000; }
  .media-status { position: absolute; top: 8px; right: 8px; display: inline-flex; align-items: center; gap: 6px; background: rgba(0,0,0,0.6); color: #fff; border-radius: 999px; padding: 4px 10px; font-size: 11px; line-height: 1; pointer-events: none; }
  .media-status i { font-size: 11px; }
  .media-status.error { background: rgba(180,40,40,0.85); }

  /* Feed items */
  .feed-list { display: flex; flex-direction: column; gap: 14px; }
  .feed-item { background: #fff; border-radius: 12px; box-shadow: 0 1px 2px rgba(0,0,0,0.08); padding: 14px 16px; position: relative; transition: box-shadow 0.3s, background 0.3s; }
  .feed-item.feed-item-highlight { background: #fffbea; box-shadow: 0 0 0 3px #fbbf24, 0 1px 2px rgba(0,0,0,0.08); }

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
  .feed-body { margin-top: 10px; font-size: 16px; line-height: 1.5; white-space: pre-wrap; word-break: break-word; }
  .feed-body-box { margin-top: 10px; padding: 10px 12px; background: #fafbfc; border-radius: 8px; font-size: 16px; line-height: 1.5; white-space: pre-wrap; word-break: break-word; }

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

  /* Media library picker */
  .lib-backdrop { position: fixed; inset: 0; width: 100%; max-width: none; margin: 0; background: rgba(0,0,0,0.45); z-index: 9998; display: none; align-items: center; justify-content: center; padding: 20px; }
  .lib-backdrop.open { display: flex; }
  .lib-modal { background: #fff; border-radius: 12px; width: 100%; max-width: 480px; max-height: 80vh; display: flex; flex-direction: column; box-shadow: 0 10px 32px rgba(0,0,0,0.22); overflow: hidden; }
  .lib-head { padding: 14px 18px; border-bottom: 1px solid #f0f2f5; display: flex; align-items: center; gap: 10px; flex: 0 0 auto; }
  .lib-head .title { font-weight: 700; flex: 1; }
  .lib-head .title i { color: var(--accent); margin-right: 6px; }
  .lib-close { background: none; border: none; cursor: pointer; padding: 6px 10px; border-radius: 6px; color: var(--muted); font-size: 16px; }
  .lib-close:hover { background: var(--hover); color: var(--text); }
  .lib-search { position: relative; padding: 10px 14px; border-bottom: 1px solid #f0f2f5; flex: 0 0 auto; }
  .lib-search i { position: absolute; left: 26px; top: 50%; transform: translateY(-50%); color: var(--muted); font-size: 13px; }
  .lib-search input { width: 100%; padding: 8px 12px 8px 32px; border: 1px solid var(--border); border-radius: 999px; font: inherit; }
  .lib-search input:focus { outline: none; border-color: var(--accent); }
  .lib-body { overflow-y: auto; padding: 8px; }
  .lib-row { display: flex; width: 100%; align-items: center; gap: 12px; padding: 8px; border: none; background: none; border-radius: 10px; cursor: pointer; text-align: left; font: inherit; }
  .lib-row:hover { background: var(--hover); }
  .lib-thumb { width: 56px; height: 42px; border-radius: 8px; overflow: hidden; flex: 0 0 auto; background: #f0f2f5; display: flex; align-items: center; justify-content: center; }
  .lib-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
  .lib-thumb .ph { color: var(--muted); font-size: 18px; }
  .lib-thumb .ph.audio { color: var(--accent); }
  .lib-meta { min-width: 0; flex: 1; }
  .lib-meta .ttl { display: block; font-weight: 600; font-size: 14px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .lib-meta .sub { display: block; color: var(--muted); font-size: 12px; }
  .lib-empty { color: var(--muted); padding: 18px; text-align: center; font-size: 13px; }
  @media (max-width: 600px) {
    .lib-backdrop { padding: 0; align-items: flex-end; }
    .lib-modal { max-width: none; border-radius: 16px 16px 0 0; max-height: 85dvh; }
    .lib-search input { font-size: 16px; }
  }

  /* Comments */
  .comments-toggle { background: none; border: none; padding: 0; font: inherit; color: var(--muted); cursor: pointer; display: inline-flex; align-items: center; gap: 6px; font-size: 13px; }
  .comments-toggle:hover { color: var(--accent); }
  .comments-toggle i { font-size: 13px; }
  .comments-toggle.hidden { display: none; }
  .views-indicator { color: var(--muted); font-size: 13px; display: inline-flex; align-items: center; gap: 6px; cursor: default; }
  .views-indicator.hidden { display: none; }
  .views-indicator i { font-size: 13px; }
  @media (max-width: 767px) {
    .comments-toggle .label-text,
    .views-indicator .label-text { display: none; }
  }
  .comments-wrap { margin: 10px -16px 0; border-top: 1px solid #f0f2f5; padding: 10px 16px 0; }
  .comments-list-box { display: none; }
  .comments-list-box.open { display: block; }
  .comments-list { display: flex; flex-direction: column; gap: 6px; }
  .pc-comment { display: flex; gap: 10px; padding: 4px 0; }
  .pc-comment .av { width: 32px; height: 32px; border-radius: 50%; flex-shrink: 0; background: #e4e6eb; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 12px; color: #65676b; overflow: hidden; }
  .pc-comment .av img { width: 100%; height: 100%; object-fit: cover; }
  .pc-comment .col { min-width: 0; flex: 1; }
  .pc-comment .bubble { background: #f0f2f5; padding: 8px 12px; border-radius: 14px; font-size: 13px; line-height: 1.4; display: inline-block; max-width: 100%; word-break: break-word; }
  .pc-comment .bubble .nm { font-weight: 600; display: block; font-size: 12px; }
  .pc-comment .bubble .nm a { color: var(--text); text-decoration: none; }
  .pc-comment .bubble .nm a:hover { text-decoration: underline; }
  .pc-comment .bubble .body { white-space: pre-wrap; }
  .pc-comment .cm-meta { font-size: 11px; color: var(--muted); padding: 2px 12px; display: flex; gap: 12px; align-items: center; }
  .pc-comment .cm-meta button { background: none; border: none; padding: 0; font: inherit; color: var(--muted); cursor: pointer; }
  .pc-comment .cm-meta button:hover { color: var(--text); text-decoration: underline; }
  .pc-comment .cm-meta .cm-respekt.active { color: var(--accent); font-weight: 700; }
  .pc-comment .cm-meta .cm-respekt-count { display: inline-flex; align-items: center; gap: 4px; color: var(--accent); font-weight: 700; }
  .pc-comment .cm-meta .cm-respekt-count i { font-size: 11px; }
  .pc-comment .cm-meta .cm-respekt-count:not(.visible) { display: none; }
  .pc-comment.thread { margin-left: 36px; }
  .pc-comment.thread .av { width: 28px; height: 28px; font-size: 11px; }
  .pc-comment .cm-edit { margin-top: 6px; }
  .pc-comment .cm-edit textarea { width: 100%; resize: vertical; border: 1px solid var(--border); border-radius: 12px; padding: 8px 12px; font-family: inherit; font-size: 13px; min-height: 50px; }
  .pc-comment .cm-edit textarea:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px rgba(24,119,242,0.15); }
  .pc-comment .cm-edit-actions { display: flex; justify-content: flex-end; gap: 6px; margin-top: 6px; }
  .comments-empty { color: var(--muted); font-size: 13px; padding: 6px 0 10px; text-align: center; }
  .comment-input { display: flex; gap: 8px; padding: 8px 0 10px; align-items: flex-end; }
  .comment-input .av { width: 32px; height: 32px; border-radius: 50%; flex-shrink: 0; background: #e4e6eb; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 12px; color: #65676b; overflow: hidden; }
  .comment-input .av img { width: 100%; height: 100%; object-fit: cover; }
  .comment-input form { flex: 1; display: flex; gap: 8px; align-items: flex-end; }
  .comment-input textarea { flex: 1; padding: 8px 14px; border: 1px solid var(--border); border-radius: 18px; font-size: 13px; font-family: inherit; resize: none; min-height: 36px; max-height: 140px; line-height: 1.4; background: #f0f2f5; }
  .comment-input textarea:focus { outline: none; background: #fff; border-color: var(--accent); box-shadow: 0 0 0 3px rgba(24,119,242,0.15); }
  .comment-input button { padding: 7px 14px; border: none; background: var(--accent); color: #fff; border-radius: 18px; cursor: pointer; font-size: 13px; font-weight: 600; }
  .comment-input button:disabled { opacity: 0.5; cursor: default; }
  .comment-input .reply-target { font-size: 11px; color: var(--muted); padding: 0 0 4px 42px; display: flex; gap: 8px; align-items: center; }
  .comment-input .reply-target button { background: none; border: none; color: var(--muted); cursor: pointer; padding: 0; font: inherit; text-decoration: underline; }
</style>
@endpush

<div class="view-header">
  <h1><i class="fa-solid fa-heart" style="color:var(--accent);margin-right:8px;"></i>Feed</h1>
  @include('partials.header-actions')
</div>

@include('partials.audio-player')

<div class="feed-shell">
  <form id="feedComposer" class="composer" autocomplete="off">
    @csrf
    <textarea id="feedComposerInput" name="body" placeholder="Hvad sker der, {{ explode(' ', trim($user->name))[0] }}?" maxlength="2000" rows="1"></textarea>
    <div id="feedComposerPreview" class="composer-preview" style="display:none;">
      <img id="feedComposerPreviewImg" src="" alt="" style="display:none;">
      <video id="feedComposerPreviewVideo" src="" controls playsinline style="display:none;"></video>
      <div id="feedComposerPreviewChip" class="composer-media-chip" style="display:none;"></div>
      <button type="button" class="composer-preview-remove" id="feedComposerPreviewRemove" aria-label="Fjern vedhæftning"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="composer-actions">
      <span id="feedComposerUploadStatus" class="composer-upload-status" style="display:none;">
        <i class="fa-solid fa-spinner fa-spin"></i>
        <span class="composer-upload-progress" id="feedComposerUploadProgress">
          <span class="bar"><span></span></span>
          <span class="pct">Overfører…</span>
        </span>
      </span>
      @if ($user->isOwner())
        <button type="button" class="composer-attach-btn" id="feedComposerLibraryBtn" aria-label="Vælg fra mediebibliotek" title="Vælg fra mediebibliotek">
          <i class="fa-solid fa-photo-film"></i>
        </button>
      @endif
      <button type="button" class="composer-attach-btn" id="feedComposerVideoBtn" aria-label="Vedhæft video" title="Vedhæft video">
        <i class="fa-solid fa-film"></i>
      </button>
      <button type="button" class="composer-attach-btn" id="feedComposerImageBtn" aria-label="Vedhæft billede" title="Vedhæft billede">
        <i class="fa-regular fa-image"></i>
      </button>
      <input type="file" id="feedComposerImageInput" accept="image/jpeg,image/png,image/gif,image/webp" style="display:none;">
      <input type="file" id="feedComposerVideoInput" accept="video/mp4,video/quicktime,video/webm,video/x-m4v,video/x-matroska,video/avi" style="display:none;">
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

@if ($user->isOwner())
<div class="lib-backdrop" id="libBackdrop" role="dialog" aria-modal="true" aria-labelledby="libTitle">
  <div class="lib-modal">
    <div class="lib-head">
      <div class="title" id="libTitle"><i class="fa-solid fa-photo-film"></i> Mediebibliotek</div>
      <button type="button" class="lib-close" id="libClose" aria-label="Luk"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="lib-search">
      <i class="fa-solid fa-magnifying-glass"></i>
      <input type="text" id="libSearch" placeholder="Søg…" autocomplete="off" aria-label="Søg i mediebiblioteket">
    </div>
    <div class="lib-body" id="libBody">
      <div class="lib-empty">Indlæser…</div>
    </div>
  </div>
</div>
@endif

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
  @php
    $viewerPayload = [
      'name' => $user->name,
      'initials' => $user->initials(),
      'picture_url' => $user->pictureUrl(),
      'profile_url' => route('members.show', $user),
    ];
  @endphp
  var VIEWER = @json($viewerPayload);
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
  var UPLOAD_IMAGE_URL = '{{ url('/api/feed/upload-image') }}';
  var UPLOAD_VIDEO_URL = '{{ url('/api/feed/upload-video') }}';

  var imageBtn = document.getElementById('feedComposerImageBtn');
  var imageInput = document.getElementById('feedComposerImageInput');
  var videoBtn = document.getElementById('feedComposerVideoBtn');
  var videoInput = document.getElementById('feedComposerVideoInput');
  var uploadStatus = document.getElementById('feedComposerUploadStatus');
  var uploadProgressEl = document.getElementById('feedComposerUploadProgress');
  var uploadProgressBar = uploadProgressEl.querySelector('.bar > span');
  var uploadProgressLabel = uploadProgressEl.querySelector('.pct');
  var preview = document.getElementById('feedComposerPreview');
  var previewImg = document.getElementById('feedComposerPreviewImg');
  var previewVideo = document.getElementById('feedComposerPreviewVideo');
  var previewChip = document.getElementById('feedComposerPreviewChip');
  var previewRemove = document.getElementById('feedComposerPreviewRemove');
  var pendingImagePath = null;
  var pendingVideoPath = null;
  var pendingMediaItem = null;
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
    el.id = it.id;
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
      if (it.video_url) {
        var st = it.video_processing_status;
        var processing = st === 'pending' || st === 'processing';
        // The video always plays from the original upload, so a failed badge is
        // only meaningful to the uploader (who can re-upload). Hide it from other
        // viewers — to them it's just noise on a video that works fine.
        var statusBadge = processing
          ? '<div class="media-status"><i class="fa-solid fa-spinner fa-spin"></i> Behandler…</div>'
          : (st === 'failed' && canManage ? '<div class="media-status error"><i class="fa-solid fa-triangle-exclamation"></i> Fejlede</div>' : '');
        body += '<div class="feed-video" data-status="' + escapeHtml(String(st || '')) + '">' +
          '<video src="' + escapeHtml(it.video_url) + '" controls preload="metadata" playsinline></video>' +
          statusBadge +
        '</div>';
      }
      if (it.media_item && it.media_item.url) {
        // No title/caption here — library names can be internal nonsense.
        var mi = it.media_item;
        if (mi.type === 'image') {
          body += '<div class="feed-image"><a href="' + escapeHtml(mi.url) + '" target="_blank" rel="noopener"><img src="' + escapeHtml(mi.url) + '" alt=""></a></div>';
        } else if (mi.type === 'video') {
          body += '<div class="feed-video">' +
            '<video src="' + escapeHtml(mi.url) + '"' + (mi.thumbnail_url ? ' poster="' + escapeHtml(mi.thumbnail_url) + '"' : '') + ' controls preload="metadata" playsinline></video>' +
            (mi.processing ? '<div class="media-status"><i class="fa-solid fa-spinner fa-spin"></i> Behandler…</div>' : '') +
            '</div>';
        } else if (mi.type === 'audio') {
          body += '<div class="feed-audio">' + tpAudio.markup(mi.url) + '</div>';
        }
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

    var canComment = !!it.can_comment;
    var commentsCount = Number(it.comments_count || 0);
    var commentsToggle = canComment
      ? '<button type="button" class="comments-toggle' + (commentsCount > 0 ? '' : ' hidden') + '" data-message-id="' + escapeHtml(String(it.target_id)) + '">' +
          '<i class="fa-regular fa-comment"></i>' +
          '<span class="comments-count-num">' + commentsCount + '</span><span class="label-text"> kommentarer</span>' +
        '</button>'
      : '';

    var isMsg = it.type === 'platform_message' || it.type === 'course_message';
    var viewsCount = Number(it.views_count || 0);
    var viewsIndicator = isMsg
      ? '<span class="views-indicator' + (viewsCount > 0 ? '' : ' hidden') + '">' +
          '<i class="fa-regular fa-eye"></i>' +
          '<span class="label-text">Set af </span><span class="views-count-num">' + viewsCount + '</span>' +
        '</span>'
      : '';

    var footer =
      '<div class="feed-footer">' +
        '<div style="display:flex;align-items:center;gap:14px;">' +
          '<button type="button" class="respekt-count"' +
            ' data-target-type="' + escapeHtml(it.target_type) + '"' +
            ' data-target-id="' + escapeHtml(String(it.target_id)) + '">' +
            (it.respekt_count > 0 ? '<i class="fa-solid fa-hand-fist"></i>' + it.respekt_count : '') +
          '</button>' +
          commentsToggle +
          viewsIndicator +
        '</div>' +
        '<button type="button" class="respekt-btn ' + (it.you_respekted ? 'active' : '') + '"' +
          ' data-target-type="' + escapeHtml(it.target_type) + '"' +
          ' data-target-id="' + escapeHtml(String(it.target_id)) + '">' +
          '<i class="fa-solid fa-hand-fist"></i> <span class="respekt-text">Respekt</span>' +
        '</button>' +
      '</div>';

    var commentsWrap = canComment
      ? '<div class="comments-wrap" data-message-id="' + escapeHtml(String(it.target_id)) + '" data-loaded="0">' +
          '<div class="comments-list-box">' +
            '<div class="comments-list"></div>' +
          '</div>' +
          '<div class="comment-input">' +
            avatar(VIEWER) +
            '<div style="flex:1;display:flex;flex-direction:column;">' +
              '<div class="reply-target" style="display:none;"></div>' +
              '<form class="comment-form">' +
                '<textarea name="body" rows="1" placeholder="Skriv en kommentar..." maxlength="2000"></textarea>' +
                '<button type="submit" disabled>Send</button>' +
              '</form>' +
            '</div>' +
          '</div>' +
        '</div>'
      : '';

    el.innerHTML = menu + head + body + footer + commentsWrap;
    if (window.tpAudio) tpAudio.init(el);
    observeCardForViews(el);
    return el;
  }

  // ---------- Views ----------
  var VIEW_URL = function (id) { return '{{ url('/api/messages') }}/' + encodeURIComponent(id) + '/view'; };
  var viewedMessageIds = new Set();
  var viewObserver = ('IntersectionObserver' in window)
    ? new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (!entry.isIntersecting) return;
          var card = entry.target;
          var type = card.dataset.type;
          if (type !== 'platform_message' && type !== 'course_message') return;
          var messageId = card.dataset.targetId;
          if (!messageId || viewedMessageIds.has(messageId)) return;
          viewedMessageIds.add(messageId);
          viewObserver.unobserve(card);
          recordView(card, messageId);
        });
      }, { threshold: 0.5 })
    : null;

  function observeCardForViews(el) {
    if (!viewObserver) return;
    var type = el.dataset.type;
    if (type !== 'platform_message' && type !== 'course_message') return;
    if (el.dataset.viewObserved === '1') return;
    el.dataset.viewObserved = '1';
    viewObserver.observe(el);
  }

  async function recordView(card, messageId) {
    try {
      var res = await fetch(VIEW_URL(messageId), {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': CSRF, Accept: 'application/json' },
      });
      if (!res.ok) return;
      var data = await res.json();
      if (!data.counted) return;
      var ind = card.querySelector('.views-indicator');
      if (!ind) return;
      var numEl = ind.querySelector('.views-count-num');
      var n = Number(numEl.textContent || 0) + 1;
      numEl.textContent = String(n);
      ind.classList.toggle('hidden', n <= 0);
    } catch (_) { /* silent */ }
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
        scrollToHashTarget();
      } else {
        paint(data.items);
      }
    } catch (e) {
      loading.textContent = 'Kunne ikke hente feedet.';
    }
  }

  function scrollToHashTarget() {
    var hash = window.location.hash;
    if (!hash || hash.length < 2) return;
    var id = hash.slice(1);
    var target = document.getElementById(id);
    if (!target) return;
    target.scrollIntoView({ behavior: 'smooth', block: 'center' });
    target.classList.add('feed-item-highlight');
    setTimeout(function () { target.classList.remove('feed-item-highlight'); }, 2400);
  }

  function refreshSubmitState() {
    var hasText = input.value.trim().length > 0;
    submit.disabled = uploading || (!hasText && !pendingImagePath && !pendingVideoPath && !pendingMediaItem);
  }
  function autosize() {
    input.style.height = 'auto';
    input.style.height = Math.min(input.scrollHeight, 240) + 'px';
    refreshSubmitState();
  }
  input.addEventListener('input', autosize);

  function clearAttachments() {
    pendingImagePath = null;
    pendingVideoPath = null;
    pendingMediaItem = null;
    preview.style.display = 'none';
    previewImg.style.display = 'none';
    previewImg.src = '';
    previewVideo.style.display = 'none';
    previewVideo.removeAttribute('src');
    previewVideo.load();
    previewChip.style.display = 'none';
    previewChip.innerHTML = '';
    imageInput.value = '';
    videoInput.value = '';
    refreshSubmitState();
  }
  previewRemove.addEventListener('click', clearAttachments);
  imageBtn.addEventListener('click', function () {
    if (uploading) return;
    imageInput.click();
  });
  videoBtn.addEventListener('click', function () {
    if (uploading) return;
    videoInput.click();
  });

  function setUploading(active, label) {
    uploading = active;
    imageBtn.disabled = active;
    videoBtn.disabled = active;
    uploadStatus.style.display = active ? 'inline-flex' : 'none';
    if (active) {
      uploadProgressBar.style.width = '0%';
      uploadProgressLabel.textContent = label || 'Overfører…';
    }
    refreshSubmitState();
  }

  function uploadWithProgress(url, formData, label) {
    return new Promise(function (resolve, reject) {
      var xhr = new XMLHttpRequest();
      xhr.open('POST', url);
      xhr.setRequestHeader('X-CSRF-TOKEN', CSRF);
      xhr.setRequestHeader('Accept', 'application/json');
      xhr.upload.addEventListener('progress', function (e) {
        if (e.lengthComputable) {
          var pct = Math.round((e.loaded / e.total) * 100);
          uploadProgressBar.style.width = pct + '%';
          uploadProgressLabel.textContent = label + ' ' + pct + '%';
        }
      });
      xhr.onload = function () {
        var data = null;
        try { data = JSON.parse(xhr.responseText); } catch (_) {}
        if (xhr.status >= 200 && xhr.status < 300) {
          resolve(data || {});
        } else {
          reject(new Error((data && data.message) ? data.message : 'Upload fejlede.'));
        }
      };
      xhr.onerror = function () { reject(new Error('Netværksfejl under upload.')); };
      xhr.send(formData);
    });
  }

  imageInput.addEventListener('change', async function () {
    var file = imageInput.files && imageInput.files[0];
    if (!file) return;
    if (pendingVideoPath || pendingMediaItem) clearAttachments();
    errBox.style.display = 'none';
    setUploading(true, 'Overfører billede');
    try {
      var fd = new FormData();
      fd.append('image', file);
      var data = await uploadWithProgress(UPLOAD_IMAGE_URL, fd, 'Overfører billede');
      pendingImagePath = data.path;
      previewImg.src = data.url;
      previewImg.style.display = 'block';
      previewVideo.style.display = 'none';
      preview.style.display = 'inline-flex';
    } catch (err) {
      errBox.textContent = err && err.message ? err.message : 'Kunne ikke uploade billedet. Prøv igen.';
      errBox.style.display = 'block';
      imageInput.value = '';
    } finally {
      setUploading(false);
    }
  });

  videoInput.addEventListener('change', async function () {
    var file = videoInput.files && videoInput.files[0];
    if (!file) return;
    if (pendingImagePath || pendingMediaItem) clearAttachments();
    errBox.style.display = 'none';
    setUploading(true, 'Overfører video');
    try {
      var fd = new FormData();
      fd.append('video', file);
      var data = await uploadWithProgress(UPLOAD_VIDEO_URL, fd, 'Overfører video');
      pendingVideoPath = data.path;
      previewVideo.src = data.url;
      previewVideo.style.display = 'block';
      previewImg.style.display = 'none';
      preview.style.display = 'inline-flex';
    } catch (err) {
      errBox.textContent = err && err.message ? err.message : 'Kunne ikke uploade videoen. Prøv igen.';
      errBox.style.display = 'block';
      videoInput.value = '';
    } finally {
      setUploading(false);
    }
  });
  // ---------- Media library picker (owners) ----------
  var libBtn = document.getElementById('feedComposerLibraryBtn');
  var libBackdrop = document.getElementById('libBackdrop');
  if (libBtn && libBackdrop) {
    var LIB_URL = '{{ url('/api/media-library') }}';
    var libBody = document.getElementById('libBody');
    var libSearch = document.getElementById('libSearch');
    var libCloseBtn = document.getElementById('libClose');
    var libItems = [];

    var libTypeLabel = { video: 'Video', audio: 'Lyd', image: 'Billede' };
    function libRender() {
      var q = (libSearch.value || '').toLowerCase().trim();
      var rows = libItems.filter(function (it) {
        return !q || ((it.title || '') + ' ' + (it.description || '')).toLowerCase().indexOf(q) !== -1;
      });
      if (!rows.length) {
        libBody.innerHTML = '<div class="lib-empty">' + (libItems.length ? 'Ingen resultater.' : 'Mediebiblioteket er tomt.') + '</div>';
        return;
      }
      libBody.innerHTML = rows.map(function (it) {
        var thumb;
        if (it.type === 'image' && it.url) thumb = '<img src="' + escapeHtml(it.url) + '" alt="">';
        else if (it.type === 'video' && it.thumbnail_url) thumb = '<img src="' + escapeHtml(it.thumbnail_url) + '" alt="">';
        else if (it.type === 'video') thumb = '<span class="ph"><i class="fa-solid fa-film"></i></span>';
        else thumb = '<span class="ph audio"><i class="fa-solid fa-music"></i></span>';
        return '<button type="button" class="lib-row" data-id="' + it.id + '">' +
          '<span class="lib-thumb">' + thumb + '</span>' +
          '<span class="lib-meta"><span class="ttl">' + escapeHtml(it.title) + '</span><span class="sub">' + (libTypeLabel[it.type] || '') + '</span></span>' +
          '</button>';
      }).join('');
    }

    async function libOpen() {
      libBackdrop.classList.add('open');
      libSearch.value = '';
      libBody.innerHTML = '<div class="lib-empty">Indlæser…</div>';
      try {
        var res = await fetch(LIB_URL, { headers: { Accept: 'application/json' }});
        if (!res.ok) throw new Error('fetch failed');
        var data = await res.json();
        libItems = data.items || [];
        libRender();
      } catch (err) {
        libBody.innerHTML = '<div class="lib-empty">Kunne ikke hente mediebiblioteket.</div>';
      }
    }
    function libClose() { libBackdrop.classList.remove('open'); }

    libBtn.addEventListener('click', function () { if (!uploading) libOpen(); });
    libCloseBtn.addEventListener('click', libClose);
    libBackdrop.addEventListener('click', function (e) { if (e.target === libBackdrop) libClose(); });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && libBackdrop.classList.contains('open')) libClose();
    });
    libSearch.addEventListener('input', libRender);

    libBody.addEventListener('click', function (e) {
      var row = e.target.closest('.lib-row');
      if (!row) return;
      var it = libItems.find(function (x) { return String(x.id) === row.dataset.id; });
      if (!it) return;
      clearAttachments();
      pendingMediaItem = it;
      if (it.type === 'image' && it.url) {
        previewImg.src = it.url;
        previewImg.style.display = 'block';
      } else if (it.type === 'video' && it.url) {
        previewVideo.src = it.url;
        previewVideo.style.display = 'block';
      } else {
        previewChip.innerHTML = '<i class="fa-solid fa-music"></i><span class="t">' + escapeHtml(it.title) + '</span>';
        previewChip.style.display = 'inline-flex';
      }
      preview.style.display = 'inline-flex';
      libClose();
      refreshSubmitState();
    });
  }

  input.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); composer.requestSubmit(); }
  });

  composer.addEventListener('submit', async function (e) {
    e.preventDefault();
    var body = input.value.trim();
    if (!body && !pendingImagePath && !pendingVideoPath && !pendingMediaItem) return;
    if (uploading) return;
    submit.disabled = true;
    errBox.style.display = 'none';
    try {
      var res = await fetch(SEND_URL, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': CSRF, Accept: 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify({ body: body, image_path: pendingImagePath, video_path: pendingVideoPath, media_item_id: pendingMediaItem ? pendingMediaItem.id : null }),
      });
      if (!res.ok) throw new Error('Send failed');
      input.value = '';
      clearAttachments();
      autosize();
      await load(false);
    } catch (err) {
      errBox.textContent = 'Kunne ikke sende. Prøv igen.';
      errBox.style.display = 'block';
      submit.disabled = false;
    }
  });

  // ---------- Comments ----------
  var COMMENTS_INDEX_URL = function (id) { return '{{ url('/api/messages') }}/' + encodeURIComponent(id) + '/comments'; };
  var COMMENT_UPDATE_URL = function (id) { return '{{ url('/api/comments') }}/' + encodeURIComponent(id); };
  var COMMENT_DELETE_URL = function (id) { return '{{ url('/api/comments') }}/' + encodeURIComponent(id) + '/delete'; };

  var OPEN_KEY = 'tp:openComments';
  var openComments = new Set((function () {
    try { return JSON.parse(localStorage.getItem(OPEN_KEY) || '[]'); } catch (_) { return []; }
  })());
  function persistOpen() {
    try { localStorage.setItem(OPEN_KEY, JSON.stringify([].slice.call(openComments))); } catch (_) {}
  }

  function renderCommentRow(c, isReply) {
    var classes = 'pc-comment' + (isReply ? ' thread' : '');
    var av = c.user.picture_url
      ? '<div class="av"><img src="' + escapeHtml(c.user.picture_url) + '" alt=""></div>'
      : '<div class="av">' + escapeHtml(c.user.initials) + '</div>';
    var nameLink = '<a href="' + escapeHtml(c.user.profile_url) + '">' + escapeHtml(c.user.name) + '</a>';
    var bubble =
      '<div class="bubble">' +
        '<span class="nm">' + nameLink + '</span>' +
        '<div class="body">' + escapeHtml(c.body) + '</div>' +
      '</div>';
    var respektActive = c.you_respekted ? ' active' : '';
    var respektCountHtml =
      '<span class="cm-respekt-count' + (c.respekt_count > 0 ? ' visible' : '') + '"' +
        ' data-target-type="comment" data-target-id="' + c.id + '">' +
        '<i class="fa-solid fa-hand-fist"></i><span class="num">' + (c.respekt_count > 0 ? c.respekt_count : '') + '</span>' +
      '</span>';
    var mineActions = c.mine ? '<button type="button" class="cm-edit-btn">Rediger</button><button type="button" class="cm-delete-btn">Slet</button>' : '';
    var replyBtn = '<button type="button" class="cm-reply-btn">Svar</button>';
    var meta =
      '<div class="cm-meta">' +
        '<button type="button" class="cm-respekt' + respektActive + '" data-target-type="comment" data-target-id="' + c.id + '">Respekt</button>' +
        replyBtn +
        mineActions +
        '<span style="flex:1;"></span>' +
        respektCountHtml +
        '<span>' + escapeHtml(c.time_human) + '</span>' +
      '</div>';

    return '<div class="' + classes + '" data-comment-id="' + c.id + '" data-parent-id="' + (c.parent_id || '') + '">' +
      av +
      '<div class="col"><div class="bubble-wrap">' + bubble + '</div>' + meta + '</div>' +
    '</div>';
  }

  function renderCommentsInto(wrap, comments) {
    var listEl = wrap.querySelector('.comments-list');
    if (!comments || !comments.length) {
      listEl.innerHTML = '<div class="comments-empty">Ingen kommentarer endnu.</div>';
      return;
    }
    // Top-level first, replies grouped after each parent.
    var byParent = {};
    var topLevel = [];
    comments.forEach(function (c) {
      if (c.parent_id) {
        (byParent[c.parent_id] = byParent[c.parent_id] || []).push(c);
      } else {
        topLevel.push(c);
      }
    });
    var html = '';
    topLevel.forEach(function (c) {
      html += renderCommentRow(c, false);
      (byParent[c.id] || []).forEach(function (r) {
        html += renderCommentRow(r, true);
      });
    });
    listEl.innerHTML = html;
  }

  async function loadComments(wrap, force) {
    if (!force && wrap.dataset.loaded === '1') return;
    var id = wrap.dataset.messageId;
    try {
      var res = await fetch(COMMENTS_INDEX_URL(id), { headers: { Accept: 'application/json' }});
      if (!res.ok) throw new Error('load failed');
      var data = await res.json();
      renderCommentsInto(wrap, data.comments);
      wrap.dataset.loaded = '1';
    } catch (err) {
      wrap.querySelector('.comments-list').innerHTML = '<div class="comments-empty">Kunne ikke hente kommentarer.</div>';
    }
  }

  function setCount(card, n) {
    var toggle = card.querySelector('.comments-toggle');
    if (!toggle) return;
    var t = toggle.querySelector('.comments-count-num');
    if (t) t.textContent = String(n);
    toggle.classList.toggle('hidden', Number(n) <= 0);
  }

  function restoreOpenComments() {
    openComments.forEach(function (id) {
      var card = document.getElementById(id);
      if (!card) return;
      var wrap = card.querySelector('.comments-wrap');
      if (!wrap) return;
      var box = wrap.querySelector('.comments-list-box');
      if (box) box.classList.add('open');
      loadComments(wrap, false);
    });
  }

  list.addEventListener('click', async function (e) {
    // Toggle
    var toggle = e.target.closest('.comments-toggle');
    if (toggle) {
      e.preventDefault();
      var card = toggle.closest('.feed-item');
      var wrap = card.querySelector('.comments-wrap');
      var box = wrap.querySelector('.comments-list-box');
      var isOpen = box.classList.toggle('open');
      if (isOpen) {
        openComments.add(card.id);
        loadComments(wrap, false);
      } else {
        openComments.delete(card.id);
      }
      persistOpen();
      return;
    }

    // Reply
    var replyBtn = e.target.closest('.cm-reply-btn');
    if (replyBtn) {
      e.preventDefault();
      var row = replyBtn.closest('.pc-comment');
      var card = row.closest('.feed-item');
      var wrap = card.querySelector('.comments-wrap');
      var rt = wrap.querySelector('.reply-target');
      var ta = wrap.querySelector('textarea');
      var name = row.querySelector('.bubble .nm a').textContent;
      rt.dataset.parentId = row.dataset.commentId;
      rt.innerHTML = 'Svarer ' + escapeHtml(name) + ' · <button type="button" class="cancel-reply">annullér</button>';
      rt.style.display = 'flex';
      ta.focus();
      return;
    }
    var cancelReply = e.target.closest('.cancel-reply');
    if (cancelReply) {
      e.preventDefault();
      var card = cancelReply.closest('.feed-item');
      var wrap = card.querySelector('.comments-wrap');
      var rt = wrap.querySelector('.reply-target');
      rt.dataset.parentId = '';
      rt.style.display = 'none';
      rt.innerHTML = '';
      return;
    }

    // Edit
    var editBtn = e.target.closest('.cm-edit-btn');
    if (editBtn) {
      e.preventDefault();
      var row = editBtn.closest('.pc-comment');
      if (row.querySelector('.cm-edit')) return;
      var bodyEl = row.querySelector('.bubble .body');
      var original = bodyEl ? bodyEl.textContent : '';
      var edit = document.createElement('div');
      edit.className = 'cm-edit';
      edit.innerHTML =
        '<textarea maxlength="2000"></textarea>' +
        '<div class="cm-edit-actions">' +
          '<button type="button" class="btn btn-secondary btn-sm cm-edit-cancel">Annullér</button>' +
          '<button type="button" class="btn btn-primary btn-sm cm-edit-save">Gem</button>' +
        '</div>';
      var ta = edit.querySelector('textarea');
      ta.value = original;
      if (bodyEl) bodyEl.closest('.bubble-wrap').appendChild(edit);
      ta.focus();
      ta.setSelectionRange(ta.value.length, ta.value.length);
      edit.querySelector('.cm-edit-cancel').addEventListener('click', function () { edit.remove(); });
      edit.querySelector('.cm-edit-save').addEventListener('click', async function () {
        var newBody = ta.value.trim();
        if (!newBody) return;
        var saveBtn = edit.querySelector('.cm-edit-save');
        saveBtn.disabled = true;
        try {
          var res = await fetch(COMMENT_UPDATE_URL(row.dataset.commentId), {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF, Accept: 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify({ body: newBody }),
          });
          if (!res.ok) throw new Error('save failed');
          var data = await res.json();
          if (bodyEl) bodyEl.textContent = data.body;
          edit.remove();
        } catch (err) {
          saveBtn.disabled = false;
        }
      });
      return;
    }

    // Delete
    var delBtn = e.target.closest('.cm-delete-btn');
    if (delBtn) {
      e.preventDefault();
      if (!confirm('Slet denne kommentar?')) return;
      var row = delBtn.closest('.pc-comment');
      var card = row.closest('.feed-item');
      try {
        var res = await fetch(COMMENT_DELETE_URL(row.dataset.commentId), {
          method: 'POST',
          headers: { 'X-CSRF-TOKEN': CSRF, Accept: 'application/json' },
        });
        if (!res.ok) throw new Error('delete failed');
        var data = await res.json();
        // Remove the row + any replies whose parent_id matches
        var parentId = row.dataset.commentId;
        Array.prototype.slice.call(card.querySelectorAll('.pc-comment')).forEach(function (r) {
          if (r === row || r.dataset.parentId === parentId) r.remove();
        });
        setCount(card, data.comments_count);
        var listEl = card.querySelector('.comments-list');
        if (listEl && !listEl.querySelector('.pc-comment')) {
          listEl.innerHTML = '<div class="comments-empty">Ingen kommentarer endnu.</div>';
        }
      } catch (err) { /* silent */ }
      return;
    }

    // Respekt on comment
    var cmRespekt = e.target.closest('.cm-respekt');
    if (cmRespekt) {
      e.preventDefault();
      var id = cmRespekt.dataset.targetId;
      cmRespekt.disabled = true;
      try {
        var res = await fetch('{{ url('/api/respekt') }}', {
          method: 'POST',
          headers: { 'X-CSRF-TOKEN': CSRF, Accept: 'application/json', 'Content-Type': 'application/json' },
          body: JSON.stringify({ target_type: 'comment', target_id: id }),
        });
        if (!res.ok) throw new Error('respekt failed');
        var data = await res.json();
        cmRespekt.classList.toggle('active', !!data.respekted);
        var row = cmRespekt.closest('.pc-comment');
        var counter = row.querySelector('.cm-respekt-count');
        if (counter) {
          var numEl = counter.querySelector('.num');
          if (numEl) numEl.textContent = data.count > 0 ? data.count : '';
          counter.classList.toggle('visible', data.count > 0);
        }
      } catch (err) { /* silent */ }
      finally { cmRespekt.disabled = false; }
      return;
    }

    // Open list of who-respekted on comment
    var cmRespCount = e.target.closest('.cm-respekt-count');
    if (cmRespCount && cmRespCount.classList.contains('visible')) {
      e.preventDefault();
      openRespModal('comment', cmRespCount.dataset.targetId);
      return;
    }
  });

  // Comment form input
  list.addEventListener('input', function (e) {
    var ta = e.target.closest('.comment-input textarea');
    if (!ta) return;
    ta.style.height = 'auto';
    ta.style.height = Math.min(ta.scrollHeight, 140) + 'px';
    var btn = ta.form.querySelector('button[type=submit]');
    if (btn) btn.disabled = !ta.value.trim();
  });
  list.addEventListener('keydown', function (e) {
    var ta = e.target.closest('.comment-input textarea');
    if (!ta) return;
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      if (ta.value.trim()) ta.form.requestSubmit();
    }
  });
  list.addEventListener('submit', async function (e) {
    var form = e.target.closest('.comment-form');
    if (!form) return;
    e.preventDefault();
    var card = form.closest('.feed-item');
    var wrap = form.closest('.comments-wrap');
    var ta = form.querySelector('textarea');
    var btn = form.querySelector('button[type=submit]');
    var body = ta.value.trim();
    if (!body) return;
    var rt = wrap.querySelector('.reply-target');
    var parentId = rt && rt.dataset.parentId ? rt.dataset.parentId : null;
    btn.disabled = true;
    try {
      var res = await fetch(COMMENTS_INDEX_URL(wrap.dataset.messageId), {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': CSRF, Accept: 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify({ body: body, parent_id: parentId }),
      });
      if (!res.ok) throw new Error('send failed');
      var data = await res.json();
      ta.value = '';
      ta.style.height = 'auto';
      if (rt) { rt.dataset.parentId = ''; rt.style.display = 'none'; rt.innerHTML = ''; }
      setCount(card, data.comments_count);
      var box = wrap.querySelector('.comments-list-box');
      if (box && !box.classList.contains('open')) {
        box.classList.add('open');
        openComments.add(card.id);
        persistOpen();
      }
      await loadComments(wrap, true);
    } catch (err) {
      btn.disabled = false;
    }
  });

  load(true).then(restoreOpenComments);
  setInterval(function () { load(false); }, 8000);
})();
</script>
@endpush

@endsection
