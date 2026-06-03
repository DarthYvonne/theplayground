@extends('layouts.app')
@section('content')

@push('styles')
<style>
  .media-shell { max-width: 720px; }

  .media-toolbar { display: flex; gap: 10px; align-items: center; margin-bottom: 14px; flex-wrap: wrap; }
  .media-search { flex: 1; min-width: 180px; position: relative; }
  .media-search i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--muted); font-size: 13px; }
  .media-search input { width: 100%; padding: 9px 12px 9px 32px; border: 1px solid var(--border); border-radius: 999px; font: inherit; background: #fff; }
  .media-search input:focus { outline: none; border-color: var(--accent); }
  .media-search .clear { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); color: var(--muted); font-size: 13px; padding: 4px; }

  .media-tabs { display: inline-flex; gap: 4px; background: #fff; border: 1px solid var(--border); border-radius: 999px; padding: 4px; }
  .media-tabs a { padding: 7px 14px; border-radius: 999px; color: var(--muted); font-weight: 600; font-size: 13px; display: inline-flex; align-items: center; gap: 6px; }
  .media-tabs a:hover { background: var(--hover); color: var(--text); }
  .media-tabs a.active { background: var(--accent); color: #fff; }

  .media-upload { background: #fff; border: 1px solid var(--border); border-radius: 12px; padding: 14px 16px; margin-bottom: 18px; box-shadow: 0 1px 2px rgba(0,0,0,0.06); }
  .media-upload summary { cursor: pointer; font-weight: 700; list-style: none; display: flex; align-items: center; gap: 8px; color: var(--text); }
  .media-upload summary::-webkit-details-marker { display: none; }
  .media-upload summary i.chev { margin-left: auto; transition: transform 0.15s; color: var(--muted); }
  .media-upload[open] summary i.chev { transform: rotate(180deg); }
  .media-upload .fields { margin-top: 14px; display: flex; flex-direction: column; gap: 10px; }
  .media-upload label { font-size: 12px; font-weight: 600; color: var(--muted); display: block; margin-bottom: 4px; }
  .media-upload input[type=text], .media-upload textarea, .media-upload input[type=file] { width: 100%; padding: 9px 11px; border: 1px solid var(--border); border-radius: 8px; font: inherit; background: #fff; }
  .media-upload textarea { resize: vertical; min-height: 60px; }
  .media-upload .req { color: var(--danger); }

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

  /* Image lightbox */
  .media-lightbox { position: fixed; inset: 0; background: rgba(0,0,0,0.85); z-index: 9998; display: none; align-items: center; justify-content: center; padding: 24px; }
  .media-lightbox.open { display: flex; }
  .media-lightbox img { max-width: 100%; max-height: 100%; border-radius: 6px; }
  .media-lightbox .close { position: absolute; top: 18px; right: 22px; color: #fff; font-size: 26px; background: none; border: none; cursor: pointer; }
</style>
@endpush

@php
  $tabs = [
    'video' => ['label' => 'Videoer', 'icon' => 'fa-film'],
    'audio' => ['label' => 'Lyd', 'icon' => 'fa-music'],
    'image' => ['label' => 'Billeder', 'icon' => 'fa-image'],
  ];
  $accept = ['video' => 'video/*', 'audio' => 'audio/*', 'image' => 'image/*'][$tab];
@endphp

<div class="view-header">
  <h1><i class="fa-solid fa-photo-film" style="color:var(--accent);margin-right:8px;"></i>Mediebibliotek</h1>
  @include('partials.header-actions')
</div>

<div class="media-shell">
  <div class="media-toolbar">
    <form method="GET" action="{{ route('media.index') }}" class="media-search">
      <input type="hidden" name="tab" value="{{ $tab }}">
      <i class="fa-solid fa-magnifying-glass"></i>
      <input type="text" name="q" value="{{ $q }}" placeholder="Søg i {{ strtolower($tabs[$tab]['label']) }}…" autocomplete="off">
      @if ($q !== '')
        <a href="{{ route('media.index', ['tab' => $tab]) }}" class="clear" aria-label="Ryd søgning"><i class="fa-solid fa-xmark"></i></a>
      @endif
    </form>
    <div class="media-tabs">
      @foreach ($tabs as $key => $t)
        <a href="{{ route('media.index', ['tab' => $key] + ($q !== '' ? ['q' => $q] : [])) }}"
           class="{{ $tab === $key ? 'active' : '' }}">
          <i class="fa-solid {{ $t['icon'] }}"></i> {{ $t['label'] }}
        </a>
      @endforeach
    </div>
  </div>

  @if ($isOwner)
    <details class="media-upload" @if ($errors->any()) open @endif>
      <summary>
        <i class="fa-solid fa-plus" style="color:var(--accent);"></i>
        Upload {{ strtolower($tabs[$tab]['label']) }}
        <i class="fa-solid fa-chevron-down chev"></i>
      </summary>
      <form method="POST" action="{{ route('media.store') }}" enctype="multipart/form-data" class="fields">
        @csrf
        <input type="hidden" name="type" value="{{ $tab }}">
        <div>
          <label>Titel <span class="req">*</span></label>
          <input type="text" name="title" value="{{ old('title') }}" maxlength="255" required>
        </div>
        <div>
          <label>Beskrivelse</label>
          <textarea name="description" maxlength="2000" placeholder="Valgfri">{{ old('description') }}</textarea>
        </div>
        <div>
          <label>Fil <span class="req">*</span></label>
          <input type="file" name="file" accept="{{ $accept }}" required>
        </div>
        <div>
          <button type="submit" class="btn btn-primary">
            <i class="fa-solid fa-upload"></i> Upload
          </button>
        </div>
      </form>
    </details>
  @endif

  @if ($items->isEmpty())
    <div class="media-empty">
      <h3>Ingen {{ strtolower($tabs[$tab]['label']) }} endnu</h3>
      <p>{{ $isOwner ? 'Upload den første ovenfor.' : 'Der er ikke uploadet noget her endnu.' }}</p>
    </div>
  @else
    <div class="media-grid">
      @foreach ($items as $item)
        <div class="media-card">
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
  @endif
</div>

<div class="media-lightbox" id="mediaLightbox">
  <button type="button" class="close" id="mediaLightboxClose" aria-label="Luk"><i class="fa-solid fa-xmark"></i></button>
  <img src="" alt="" id="mediaLightboxImg">
</div>

@push('scripts')
<script>
(function () {
  var box = document.getElementById('mediaLightbox');
  var img = document.getElementById('mediaLightboxImg');
  var closeBtn = document.getElementById('mediaLightboxClose');
  function close() { box.classList.remove('open'); img.src = ''; }
  document.querySelectorAll('.media-card img.thumb[data-full]').forEach(function (el) {
    el.addEventListener('click', function () {
      img.src = el.dataset.full;
      box.classList.add('open');
    });
  });
  closeBtn.addEventListener('click', close);
  box.addEventListener('click', function (e) { if (e.target === box) close(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && box.classList.contains('open')) close(); });
})();
</script>
@endpush

@endsection
