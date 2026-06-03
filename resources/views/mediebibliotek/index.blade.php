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
  .media-nav a.active { color: var(--accent); }
  .media-nav a.active .cnt { color: var(--accent); }
  .media-nav a::before { content: "|"; color: var(--border); margin: 0 10px; font-weight: 400; }
  .media-nav a.lead::before { content: none; }
  .media-nav a .cnt { color: var(--text); font-weight: 700; }

  /* Playlist cards — each playlist is a card; play all or one at a time */
  .pl-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 12px; }
  @media (max-width: 600px) { .pl-grid { grid-template-columns: 1fr; } }
  .pl-cover { display: block; width: 100%; height: 120px; object-fit: cover; background: #f0f2f5; }
  .pl-head { display: flex; align-items: center; gap: 10px; padding: 12px 14px; border-bottom: 1px solid #f0f2f5; }
  .pl-name { font-weight: 700; font-size: 14px; flex: 1; min-width: 0; display: flex; gap: 6px; align-items: center; }
  .pl-name .nm { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .pl-name .pl-count { color: var(--muted); font-weight: 600; }
  .pl-desc { padding: 10px 14px; border-bottom: 1px solid #f0f2f5; color: var(--muted); font-size: 12px; line-height: 1.45; white-space: pre-wrap; }
  .pl-actions { display: flex; align-items: center; gap: 2px; flex: 0 0 auto; }
  .pl-actions .ibtn { background: none; border: none; cursor: pointer; color: var(--muted); padding: 6px 8px; border-radius: 6px; font-size: 13px; }
  .pl-actions .ibtn:hover { background: var(--hover); color: var(--accent); }
  .pl-actions .ibtn.danger:hover { background: #fdeaea; color: var(--danger); }
  .pl-actions form { display: inline-flex; margin: 0; }
  .pl-playall { width: 36px; height: 36px; border: none; border-radius: 50%; background: linear-gradient(135deg, #4d97ff 0%, #1664d8 100%); color: #fff; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; font-size: 13px; flex: 0 0 auto; box-shadow: 0 2px 8px rgba(24,119,242,0.35); transition: transform 0.12s, box-shadow 0.12s; }
  .pl-playall:hover { transform: scale(1.07); box-shadow: 0 4px 12px rgba(24,119,242,0.45); }
  .pl-playall:active { transform: scale(0.96); }
  .pl-playall i { margin-left: 2px; }
  .pl-playall i.fa-pause { margin-left: 0; }
  .pl-tracks { display: flex; flex-direction: column; padding: 6px; flex: 1; }
  .pl-track { display: flex; align-items: center; gap: 10px; padding: 8px 10px; border: none; background: none; border-radius: 8px; font: inherit; text-align: left; cursor: pointer; color: var(--text); width: 100%; }
  .pl-track:hover { background: var(--hover); }
  .pl-track + .pl-track { border-top: 1px solid #f0f2f5; border-radius: 0 0 8px 8px; }
  .pl-track + .pl-track:not(:last-child) { border-radius: 0; }
  .pl-track:first-child:not(:last-child) { border-radius: 8px 8px 0 0; }
  .pl-track .ticon { width: 26px; height: 26px; border-radius: 50%; background: var(--accent-soft); color: var(--accent); display: inline-flex; align-items: center; justify-content: center; font-size: 10px; flex: 0 0 auto; }
  .pl-track .tthumb { width: 42px; height: 26px; border-radius: 6px; object-fit: cover; flex: 0 0 auto; background: #f0f2f5; display: block; }
  .pl-track .t { flex: 1; min-width: 0; font-size: 13px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .pl-track.playing { background: var(--accent-soft); }
  .pl-track.playing .ticon { background: var(--accent); color: #fff; }
  .pl-track.na { cursor: default; color: var(--muted); }
  .pl-track.na:hover { background: none; }
  .pl-track.na .ticon { background: #f0f2f5; color: var(--muted); }
  .pl-player { display: none; align-items: center; gap: 10px; padding: 10px 14px; border-top: 1px solid #f0f2f5; }
  .pl-player.on { display: flex; }
  .pl-bar { flex: 1; height: 6px; background: rgba(24,119,242,0.14); border-radius: 3px; cursor: pointer; position: relative; }
  .pl-bar::before { content: ""; position: absolute; inset: -8px 0; }
  .pl-prog { height: 100%; width: 0; background: linear-gradient(90deg, #4d97ff, #1877f2); border-radius: 3px; pointer-events: none; }
  .pl-time { font-size: 11px; color: var(--muted); font-variant-numeric: tabular-nums; font-weight: 600; flex: 0 0 auto; }

  .media-section { margin-bottom: 26px; }
  .media-section > h2 { font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.4px; color: var(--muted); margin-bottom: 10px; }

  .media-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 12px; }
  .media-card { background: #fff; border: 1px solid var(--border); border-radius: 12px; overflow: hidden; box-shadow: 0 1px 2px rgba(0,0,0,0.06); display: flex; flex-direction: column; }
  .media-card .body { padding: 10px 12px; display: flex; flex-direction: column; flex: 1; }
  .media-card .title { font-weight: 700; font-size: 13px; line-height: 1.3; display: flex; align-items: flex-start; gap: 6px; }
  .media-card .title .txt { flex: 1; }
  .media-card .desc { color: var(--muted); font-size: 12px; margin-top: 2px; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
  .media-card[data-id] .body { cursor: pointer; }
  .media-card[data-id] .body:hover .title .txt { color: var(--accent); }
  .media-card .meta { color: var(--muted); font-size: 11px; margin-top: auto; padding-top: 6px; }
  .media-card video, .media-card img.thumb { display: block; width: 100%; height: 130px; background: #000; object-fit: contain; }
  .media-card img.thumb { background: #f0f2f5; object-fit: cover; cursor: zoom-in; }
  .media-card .audio-wrap { padding: 10px 12px 0; }
  .media-card .state { height: 130px; display: flex; align-items: center; justify-content: center; gap: 8px; text-align: center; color: var(--muted); font-size: 12px; background: #f0f2f5; padding: 0 12px; }
  .media-card .state.failed { color: var(--danger); }
  .media-card .del { background: none; border: none; cursor: pointer; color: var(--muted); padding: 2px 6px; border-radius: 6px; font-size: 13px; }
  .media-card .del:hover { background: #fdeaea; color: var(--danger); }
  @media (max-width: 480px) {
    .media-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
    .media-card video, .media-card img.thumb, .media-card .state { height: 110px; }
  }

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
  .media-modal input[type=text], .media-modal textarea, .media-modal input[type=file], .media-modal select { width: 100%; padding: 9px 11px; border: 1px solid var(--border); border-radius: 8px; font: inherit; background: #fff; }
  .media-modal input[type=text]:focus, .media-modal textarea:focus, .media-modal select:focus { outline: none; border-color: var(--accent); }
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
    .media-modal input[type=text], .media-modal textarea, .media-modal select { font-size: 16px; }
    .media-modal .foot { padding-bottom: calc(16px + env(safe-area-inset-bottom)); }
  }

  /* Image lightbox */
  .media-lightbox { position: fixed; inset: 0; width: 100%; max-width: none; background: rgba(0,0,0,0.85); z-index: 9998; display: none; align-items: center; justify-content: center; padding: 24px; }
  .media-lightbox.open { display: flex; }
  .media-lightbox img, .media-lightbox video { max-width: 100%; max-height: 100%; border-radius: 6px; }
  .media-lightbox .close { position: absolute; top: 18px; right: 22px; color: #fff; font-size: 26px; background: none; border: none; cursor: pointer; }
</style>
@endpush

@php
  $labels = ['video' => 'Video', 'audio' => 'Lyd', 'image' => 'Billeder'];
  $hasAny = collect($groups)->flatten()->isNotEmpty();
  $playlistsWithItems = $playlists->filter(fn ($p) => $p->mediaItems->isNotEmpty())->values();
@endphp

<div class="view-header">
  <h1><i class="fa-solid fa-photo-film" style="color:var(--accent);margin-right:8px;"></i>Mediebibliotek</h1>
  @include('partials.header-actions')
</div>

@include('partials.audio-player')

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
        @if ($playlistsWithItems->isNotEmpty())
          <a href="#sec-playlists" data-type="playlists" class="{{ $first ? 'lead' : '' }}">Playlister (<span class="cnt">{{ $playlistsWithItems->count() }}</span>)</a>
          @php $first = false; @endphp
        @endif
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
            <div class="media-card" data-search="{{ \Illuminate\Support\Str::lower(trim($item->title . ' ' . $item->description . ' ' . $item->created_at->format('d.m.Y'))) }}"
              data-playlists="{{ $item->playlists->pluck('id')->implode(',') }}"
              @if ($isOwner) data-id="{{ $item->id }}" data-title="{{ $item->title }}" data-desc="{{ $item->description }}" @endif>
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
                  <div class="tp-audio sm" data-src="{{ $item->url() }}"></div>
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

    @if ($playlistsWithItems->isNotEmpty())
      <section class="media-section" id="sec-playlists" data-type="playlists">
        <h2>Playlister</h2>
        <div class="pl-grid">
          @foreach ($playlistsWithItems as $pl)
            <div class="media-card pl-card" data-search="{{ \Illuminate\Support\Str::lower(trim($pl->name . ' ' . $pl->description . ' ' . $pl->mediaItems->pluck('title')->implode(' '))) }}"
              @if ($isOwner) data-pl-id="{{ $pl->id }}" data-pl-name="{{ $pl->name }}" data-pl-desc="{{ $pl->description }}" @endif>
              @if ($pl->imageUrl())
                <img class="pl-cover" src="{{ $pl->imageUrl() }}" alt="" loading="lazy">
              @endif
              <div class="pl-head">
                @if ($pl->mediaItems->contains(fn ($mi) => $mi->type === 'audio' && $mi->url()))
                  <button type="button" class="pl-playall" aria-label="Afspil alle" title="Afspil alle"><i class="fa-solid fa-play"></i></button>
                @endif
                <div class="pl-name"><span class="nm">{{ $pl->name }}</span> <span class="pl-count">({{ $pl->mediaItems->count() }})</span></div>
                @if ($isOwner)
                  <div class="pl-actions">
                    <button type="button" class="ibtn pl-edit" title="Rediger playliste" aria-label="Rediger playliste"><i class="fa-solid fa-pen"></i></button>
                    <form method="POST" action="{{ route('media.playlists.destroy', $pl) }}" onsubmit="return confirm('Slet playlisten “{{ $pl->name }}”? Medierne i den slettes ikke.');">
                      @csrf
                      <button type="submit" class="ibtn danger" title="Slet playliste" aria-label="Slet playliste"><i class="fa-solid fa-trash"></i></button>
                    </form>
                  </div>
                @endif
              </div>
              @if (trim((string) $pl->description) !== '')
                <div class="pl-desc">{{ $pl->description }}</div>
              @endif
              <div class="pl-tracks">
                @foreach ($pl->mediaItems as $mi)
                  @php $tinyThumb = $mi->type === 'video' ? $mi->thumbnailUrl() : ($mi->type === 'image' ? $mi->url() : null); @endphp
                  @if ($mi->type === 'audio' && $mi->url())
                    <button type="button" class="pl-track" data-src="{{ $mi->url() }}">
                      <span class="ticon"><i class="fa-solid fa-play"></i></span>
                      <span class="t">{{ $mi->title }}</span>
                    </button>
                  @elseif ($mi->type === 'video' && $mi->url())
                    <button type="button" class="pl-track" data-video="{{ $mi->url() }}">
                      @if ($tinyThumb)<img class="tthumb" src="{{ $tinyThumb }}" alt="" loading="lazy">@else<span class="ticon"><i class="fa-solid fa-film"></i></span>@endif
                      <span class="t">{{ $mi->title }}</span>
                    </button>
                  @elseif ($mi->type === 'image' && $mi->url())
                    <button type="button" class="pl-track" data-image="{{ $mi->url() }}">
                      <img class="tthumb" src="{{ $mi->url() }}" alt="" loading="lazy">
                      <span class="t">{{ $mi->title }}</span>
                    </button>
                  @else
                    <div class="pl-track na">
                      <span class="ticon"><i class="fa-solid {{ $mi->type === 'video' ? 'fa-film' : ($mi->type === 'image' ? 'fa-image' : 'fa-music') }}"></i></span>
                      <span class="t">{{ $mi->title }}</span>
                    </div>
                  @endif
                @endforeach
              </div>
              <div class="pl-player">
                <div class="pl-bar"><div class="pl-prog"></div></div>
                <span class="pl-time">0:00</span>
              </div>
            </div>
          @endforeach
        </div>
      </section>
    @endif
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
          <div>
            <label for="uploadPlaylist">Tilføj til playliste?</label>
            <select name="playlist_id" id="uploadPlaylist">
              <option value="">Ingen</option>
              @foreach ($playlists as $pl)
                <option value="{{ $pl->id }}" @selected(old('playlist_id') == $pl->id)>{{ $pl->name }}</option>
              @endforeach
              <option value="new" @selected(old('playlist_id') === 'new')>+ Opret ny…</option>
            </select>
            <input type="text" name="new_playlist" id="uploadNewPlaylist" value="{{ old('new_playlist') }}" maxlength="100"
              placeholder="Navn på den nye playliste" style="margin-top:8px; {{ old('playlist_id') === 'new' ? '' : 'display:none;' }}">
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

  <div class="media-modal-backdrop" id="editBackdrop" role="dialog" aria-modal="true" aria-labelledby="editModalTitle">
    <div class="media-modal">
      <div class="head">
        <div class="title" id="editModalTitle">Rediger medie</div>
        <button type="button" class="close" id="editClose" aria-label="Luk"><i class="fa-solid fa-xmark"></i></button>
      </div>
      <form method="POST" action="" id="editForm">
        @csrf
        <div class="mbody">
          <div>
            <label for="editTitleInput">Titel <span class="req">*</span></label>
            <input type="text" name="title" id="editTitleInput" maxlength="255" required>
          </div>
          <div>
            <label for="editDesc">Beskrivelse</label>
            <textarea name="description" id="editDesc" maxlength="2000" placeholder="Valgfri"></textarea>
          </div>
          <div>
            <label for="editPlaylist">Playliste</label>
            <select name="playlist_id" id="editPlaylist">
              <option value="">Ingen</option>
              @foreach ($playlists as $pl)
                <option value="{{ $pl->id }}">{{ $pl->name }}</option>
              @endforeach
              <option value="new">+ Opret ny…</option>
            </select>
            <input type="text" name="new_playlist" id="editNewPlaylist" maxlength="100"
              placeholder="Navn på den nye playliste" style="margin-top:8px; display:none;">
          </div>
        </div>
        <div class="foot">
          <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Gem</button>
        </div>
      </form>
    </div>
  </div>

  <div class="media-modal-backdrop" id="plEditBackdrop" role="dialog" aria-modal="true" aria-labelledby="plEditTitle">
    <div class="media-modal">
      <div class="head">
        <div class="title" id="plEditTitle">Rediger playliste</div>
        <button type="button" class="close" id="plEditClose" aria-label="Luk"><i class="fa-solid fa-xmark"></i></button>
      </div>
      <form method="POST" action="" enctype="multipart/form-data" id="plEditForm">
        @csrf
        <div class="mbody">
          <div>
            <label for="plEditName">Navn <span class="req">*</span></label>
            <input type="text" name="name" id="plEditName" maxlength="100" required>
          </div>
          <div>
            <label for="plEditDesc">Beskrivelse</label>
            <textarea name="description" id="plEditDesc" maxlength="2000" placeholder="Valgfri"></textarea>
          </div>
          <div>
            <label for="plEditImage">Billede</label>
            <input type="file" name="image" id="plEditImage" accept="image/*">
          </div>
        </div>
        <div class="foot">
          <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Gem</button>
        </div>
      </form>
    </div>
  </div>
@endif

<div class="media-lightbox" id="mediaLightbox">
  <button type="button" class="close" id="mediaLightboxClose" aria-label="Luk"><i class="fa-solid fa-xmark"></i></button>
  <img src="" alt="" id="mediaLightboxImg" style="display:none;">
  <video id="mediaLightboxVideo" controls playsinline style="display:none;"></video>
</div>

@push('scripts')
<script>
(function () {
  // ---- Styled audio players ----
  if (window.tpAudio) tpAudio.init();

  // ---- Live search across all groups ----
  var search = document.getElementById('mediaSearch');
  var clearBtn = document.getElementById('mediaSearchClear');
  var noResults = document.getElementById('mediaNoResults');
  var sections = Array.prototype.slice.call(document.querySelectorAll('.media-section'));
  var navLinks = {};
  var activeType = null; // category filter (incl. "playlists") — null shows everything

  document.querySelectorAll('#mediaNav a[data-type]').forEach(function (a) {
    navLinks[a.dataset.type] = { el: a, cnt: a.querySelector('.cnt') };
    // Filter in place — no navigation, no scrolling. Clicking the active
    // category again shows everything.
    a.addEventListener('click', function (e) {
      e.preventDefault();
      activeType = (activeType === a.dataset.type) ? null : a.dataset.type;
      apply();
    });
  });

  function apply() {
    var q = (search.value || '').toLowerCase().trim();
    clearBtn.style.display = q ? 'block' : 'none';

    var totalVisible = 0;

    sections.forEach(function (sec) {
      var matches = 0;
      sec.querySelectorAll('.media-card').forEach(function (card) {
        var match = q === '' || (card.getAttribute('data-search') || '').indexOf(q) !== -1;
        card.style.display = match ? '' : 'none';
        if (match) matches++;
      });
      // Search looks across all categories; the category filter only applies
      // when not searching.
      var show = matches > 0 && (q !== '' || !activeType || sec.dataset.type === activeType);
      sec.style.display = show ? '' : 'none';
      totalVisible += show ? matches : 0;

      var link = navLinks[sec.dataset.type];
      if (link) {
        if (link.cnt) link.cnt.textContent = matches;
        link.el.style.display = matches ? '' : 'none';
        link.el.classList.toggle('active', q === '' && activeType === sec.dataset.type);
      }
    });

    // First *visible* nav link loses its "|" separator.
    var firstSeen = false;
    document.querySelectorAll('#mediaNav a').forEach(function (a) {
      var visible = a.style.display !== 'none';
      a.classList.toggle('lead', visible && !firstSeen);
      if (visible) firstSeen = true;
    });

    if (noResults) noResults.style.display = (q !== '' && totalVisible === 0) ? '' : 'none';
  }

  if (search) {
    search.addEventListener('input', function () {
      // Typing a search resets the filter — søg searches everything.
      if (search.value.trim() !== '') activeType = null;
      apply();
    });
    clearBtn.addEventListener('click', function () { search.value = ''; apply(); search.focus(); });
    apply();
  }

  // ---- Playlist cards: play all (auto-advance) or one at a time ----
  function fmtTime(s) {
    if (!isFinite(s) || s < 0) return '0:00';
    s = Math.floor(s);
    return Math.floor(s / 60) + ':' + (s % 60 < 10 ? '0' : '') + (s % 60);
  }
  document.querySelectorAll('.pl-card').forEach(function (card) {
    var tracks = Array.prototype.slice.call(card.querySelectorAll('button.pl-track[data-src]'));
    if (!tracks.length) return;
    var audio = new Audio();
    audio.preload = 'none';
    if (window.tpAudio && tpAudio.register) tpAudio.register(audio);
    var playAll = card.querySelector('.pl-playall');
    var player = card.querySelector('.pl-player');
    var bar = card.querySelector('.pl-bar');
    var prog = card.querySelector('.pl-prog');
    var time = card.querySelector('.pl-time');
    var current = -1;
    var queue = false; // "Afspil alle" mode — advance to the next track on end

    function refresh() {
      tracks.forEach(function (t, j) {
        var isCurrent = j === current;
        t.classList.toggle('playing', isCurrent);
        t.querySelector('.ticon i').className = 'fa-solid ' + (isCurrent && !audio.paused ? 'fa-pause' : 'fa-play');
      });
      if (playAll) {
        var pausing = !audio.paused && queue;
        playAll.innerHTML = pausing ? '<i class="fa-solid fa-pause"></i>' : '<i class="fa-solid fa-play"></i>';
        playAll.setAttribute('aria-label', pausing ? 'Pause' : 'Afspil alle');
        playAll.title = pausing ? 'Pause' : 'Afspil alle';
      }
    }
    function playIndex(i) {
      current = i;
      audio.src = tracks[i].dataset.src;
      audio.play();
      player.classList.add('on');
      prog.style.width = '0%';
    }

    tracks.forEach(function (t, i) {
      t.addEventListener('click', function () {
        if (current === i) { audio.paused ? audio.play() : audio.pause(); }
        // Jump freely — if "Afspil alle" is running, the queue continues from here.
        else { playIndex(i); }
      });
    });
    if (playAll) {
      playAll.addEventListener('click', function () {
        if (queue && current !== -1) { audio.paused ? audio.play() : audio.pause(); }
        else { queue = true; playIndex(0); }
      });
    }

    audio.addEventListener('play', function () {
      if (window.tpAudio && tpAudio.pauseOthers) tpAudio.pauseOthers(audio);
      refresh();
    });
    audio.addEventListener('pause', refresh);
    audio.addEventListener('ended', function () {
      if (queue && current + 1 < tracks.length) playIndex(current + 1);
      else { audio.currentTime = 0; queue = false; refresh(); }
    });
    audio.addEventListener('loadedmetadata', function () { time.textContent = '0:00 / ' + fmtTime(audio.duration); });
    audio.addEventListener('timeupdate', function () {
      if (audio.duration) prog.style.width = (audio.currentTime / audio.duration * 100) + '%';
      time.textContent = fmtTime(audio.currentTime) + (isFinite(audio.duration) ? ' / ' + fmtTime(audio.duration) : '');
    });
    bar.addEventListener('click', function (e) {
      if (!audio.duration) return;
      var r = bar.getBoundingClientRect();
      audio.currentTime = Math.min(Math.max((e.clientX - r.left) / r.width, 0), 1) * audio.duration;
    });
  });

  // ---- "Tilføj til playliste?" — reveal the name input on "Opret ny…" ----
  function wirePlaylistSelect(select, input) {
    if (!select || !input) return;
    function sync(focus) {
      var isNew = select.value === 'new';
      input.style.display = isNew ? '' : 'none';
      input.required = isNew; // keep validation client-side so modals don't mis-reopen
      if (isNew && focus) input.focus();
    }
    select.addEventListener('change', function () { sync(true); });
    sync(false);
  }
  wirePlaylistSelect(document.getElementById('uploadPlaylist'), document.getElementById('uploadNewPlaylist'));
  wirePlaylistSelect(document.getElementById('editPlaylist'), document.getElementById('editNewPlaylist'));

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

  // ---- Edit modal (owners) ----
  var editBackdrop = document.getElementById('editBackdrop');
  if (editBackdrop) {
    var editForm = document.getElementById('editForm');
    var editTitleInput = document.getElementById('editTitleInput');
    var editDescInput = document.getElementById('editDesc');
    var EDIT_URL = '{{ route('media.update', ['mediaItem' => '__ID__']) }}';

    function openEdit(card) {
      editForm.action = EDIT_URL.replace('__ID__', card.dataset.id);
      editTitleInput.value = card.dataset.title || '';
      editDescInput.value = card.dataset.desc || '';
      // Preselect the item's playlist (the UI assigns at most one).
      var plSelect = document.getElementById('editPlaylist');
      var plNew = document.getElementById('editNewPlaylist');
      if (plSelect) {
        var current = (card.dataset.playlists || '').split(',').filter(Boolean)[0] || '';
        plSelect.value = current;
        if (plSelect.value !== current) plSelect.value = '';
        plNew.value = '';
        plNew.style.display = 'none';
        plNew.required = false;
      }
      editBackdrop.classList.add('open');
      setTimeout(function () { editTitleInput.focus(); }, 50);
    }
    function closeEdit() { editBackdrop.classList.remove('open'); }

    document.getElementById('editClose').addEventListener('click', closeEdit);
    editBackdrop.addEventListener('click', function (e) { if (e.target === editBackdrop) closeEdit(); });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && editBackdrop.classList.contains('open')) closeEdit();
    });

    // Clicking a card's text area opens edit. Media (video/audio/image) and the
    // delete button keep their own click behavior.
    document.querySelectorAll('.media-card[data-id] .body').forEach(function (body) {
      body.addEventListener('click', function (e) {
        if (e.target.closest('form')) return; // the delete form
        openEdit(body.closest('.media-card'));
      });
    });
  }

  // ---- Lightbox (images + videos) ----
  var box = document.getElementById('mediaLightbox');
  var img = document.getElementById('mediaLightboxImg');
  var lbVideo = document.getElementById('mediaLightboxVideo');
  var closeBtn = document.getElementById('mediaLightboxClose');
  function closeBox() {
    box.classList.remove('open');
    img.style.display = 'none';
    img.src = '';
    lbVideo.pause();
    lbVideo.removeAttribute('src');
    lbVideo.load();
    lbVideo.style.display = 'none';
  }
  function openImage(src) {
    img.src = src;
    img.style.display = 'block';
    lbVideo.style.display = 'none';
    box.classList.add('open');
  }
  function openVideo(src) {
    if (window.tpAudio && tpAudio.pauseOthers) tpAudio.pauseOthers(lbVideo); // stop any audio
    lbVideo.src = src;
    lbVideo.style.display = 'block';
    img.style.display = 'none';
    box.classList.add('open');
    lbVideo.play();
  }
  document.querySelectorAll('.media-card img.thumb[data-full]').forEach(function (el) {
    el.addEventListener('click', function () { openImage(el.dataset.full); });
  });
  // Playlist rows: any entry can be selected — video/image open in the lightbox.
  document.querySelectorAll('.pl-track[data-video]').forEach(function (el) {
    el.addEventListener('click', function () { openVideo(el.dataset.video); });
  });
  document.querySelectorAll('.pl-track[data-image]').forEach(function (el) {
    el.addEventListener('click', function () { openImage(el.dataset.image); });
  });
  closeBtn.addEventListener('click', closeBox);
  box.addEventListener('click', function (e) { if (e.target === box) closeBox(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && box.classList.contains('open')) closeBox(); });

  // ---- Playlist edit modal (owners) ----
  var plEditBackdrop = document.getElementById('plEditBackdrop');
  if (plEditBackdrop) {
    var plEditForm = document.getElementById('plEditForm');
    var plEditName = document.getElementById('plEditName');
    var plEditDesc = document.getElementById('plEditDesc');
    var plEditImage = document.getElementById('plEditImage');
    var PL_EDIT_URL = '{{ route('media.playlists.update', ['playlist' => '__ID__']) }}';

    function closePlEdit() { plEditBackdrop.classList.remove('open'); }
    document.getElementById('plEditClose').addEventListener('click', closePlEdit);
    plEditBackdrop.addEventListener('click', function (e) { if (e.target === plEditBackdrop) closePlEdit(); });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && plEditBackdrop.classList.contains('open')) closePlEdit();
    });

    document.querySelectorAll('.pl-card .pl-edit').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var card = btn.closest('.pl-card');
        plEditForm.action = PL_EDIT_URL.replace('__ID__', card.dataset.plId);
        plEditName.value = card.dataset.plName || '';
        plEditDesc.value = card.dataset.plDesc || '';
        plEditImage.value = '';
        plEditBackdrop.classList.add('open');
        setTimeout(function () { plEditName.focus(); }, 50);
      });
    });
  }
})();
</script>
@endpush

@endsection
