{{-- Shared media picker (feed + Hold). Include once per page; open it with
     mediaPicker.open(function (sel) { ... }) where sel is
     { kind: 'item', item: {...} } or { kind: 'playlist', playlist: {...} }. --}}
@once
@push('styles')
<style>
  .mp-backdrop { position: fixed; inset: 0; width: 100%; max-width: none; margin: 0; background: rgba(0,0,0,0.45); z-index: 9998; display: none; align-items: center; justify-content: center; padding: 20px; }
  .mp-backdrop.open { display: flex; }
  .mp-modal { background: #fff; border-radius: 12px; width: 100%; max-width: 480px; max-height: 80vh; display: flex; flex-direction: column; box-shadow: 0 10px 32px rgba(0,0,0,0.22); overflow: hidden; }
  .mp-head { padding: 14px 18px; border-bottom: 1px solid #f0f2f5; display: flex; align-items: center; gap: 10px; flex: 0 0 auto; }
  .mp-head .title { font-weight: 700; flex: 1; }
  .mp-head .title i { color: var(--accent); margin-right: 6px; }
  .mp-close { background: none; border: none; cursor: pointer; padding: 6px 10px; border-radius: 6px; color: var(--muted); font-size: 16px; }
  .mp-close:hover { background: var(--hover); color: var(--text); }
  .mp-search { position: relative; padding: 10px 14px 8px; flex: 0 0 auto; }
  .mp-search i { position: absolute; left: 26px; top: 50%; transform: translateY(-4px); color: var(--muted); font-size: 13px; }
  .mp-search input { width: 100%; padding: 8px 12px 8px 32px; border: 1px solid var(--border); border-radius: 999px; font: inherit; }
  .mp-search input:focus { outline: none; border-color: var(--accent); }
  .mp-nav { display: flex; flex-wrap: wrap; align-items: center; padding: 0 14px 10px; border-bottom: 1px solid #f0f2f5; font-size: 13px; flex: 0 0 auto; }
  .mp-nav a { color: var(--muted); font-weight: 600; padding: 2px 0; text-decoration: none; }
  .mp-nav a:hover { color: var(--accent); }
  .mp-nav a.active { color: var(--accent); }
  .mp-nav a::before { content: "|"; color: var(--border); margin: 0 8px; font-weight: 400; }
  .mp-nav a.lead::before { content: none; }
  .mp-body { overflow-y: auto; padding: 8px; }
  .mp-sec { padding: 8px 8px 4px; font-size: 11px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: 0.4px; }
  .mp-row { display: flex; width: 100%; align-items: center; gap: 12px; padding: 8px; border: none; background: none; border-radius: 10px; cursor: pointer; text-align: left; font: inherit; }
  .mp-row:hover { background: var(--hover); }
  .mp-thumb { width: 56px; height: 42px; border-radius: 8px; overflow: hidden; flex: 0 0 auto; background: #f0f2f5; display: flex; align-items: center; justify-content: center; }
  .mp-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
  .mp-thumb .ph { color: var(--muted); font-size: 18px; }
  .mp-thumb .ph.audio, .mp-thumb .ph.playlist { color: var(--accent); }
  .mp-meta { min-width: 0; flex: 1; }
  .mp-meta .ttl { display: block; font-weight: 600; font-size: 14px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .mp-meta .sub { display: block; color: var(--muted); font-size: 12px; }
  .mp-empty { color: var(--muted); padding: 18px; text-align: center; font-size: 13px; }
  @media (max-width: 600px) {
    .mp-backdrop { padding: 0; align-items: flex-end; }
    .mp-modal { max-width: none; border-radius: 16px 16px 0 0; max-height: 85dvh; }
    .mp-search input { font-size: 16px; }
  }
</style>
@endpush

<div class="mp-backdrop" id="mpBackdrop" role="dialog" aria-modal="true" aria-labelledby="mpTitle">
  <div class="mp-modal">
    <div class="mp-head">
      <div class="title" id="mpTitle"><i class="fa-solid fa-photo-film"></i> Mediebibliotek</div>
      <button type="button" class="mp-close" id="mpClose" aria-label="Luk"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="mp-search">
      <i class="fa-solid fa-magnifying-glass"></i>
      <input type="text" id="mpSearch" placeholder="Søg…" autocomplete="off" aria-label="Søg i mediebiblioteket">
    </div>
    <nav class="mp-nav" id="mpNav"></nav>
    <div class="mp-body" id="mpBody">
      <div class="mp-empty">Indlæser…</div>
    </div>
  </div>
</div>

@push('scripts')
<script>
window.mediaPicker = (function () {
  var LIB_URL = '{{ url('/api/media-library') }}';
  var backdrop = document.getElementById('mpBackdrop');
  var body = document.getElementById('mpBody');
  var nav = document.getElementById('mpNav');
  var search = document.getElementById('mpSearch');
  var items = [], playlists = [];
  var filter = null; // 'video' | 'audio' | 'image' | 'playlists' | null
  var onPick = null;
  var GROUPS = [
    { key: 'video', label: 'Video' },
    { key: 'audio', label: 'Lyd' },
    { key: 'image', label: 'Billeder' },
    { key: 'playlists', label: 'Playlister' },
  ];

  function esc(s) { return String(s ?? '').replace(/[&<>"']/g, function (m) { return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[m]; }); }
  function matches(hay, q) { return !q || hay.toLowerCase().indexOf(q) !== -1; }

  function thumbFor(it) {
    if (it.type === 'image' && it.url) return '<img src="' + esc(it.url) + '" alt="">';
    if (it.type === 'video' && it.thumbnail_url) return '<img src="' + esc(it.thumbnail_url) + '" alt="">';
    if (it.type === 'video') return '<span class="ph"><i class="fa-solid fa-film"></i></span>';
    return '<span class="ph audio"><i class="fa-solid fa-music"></i></span>';
  }
  function plThumb(pl) {
    if (pl.image_url) return '<img src="' + esc(pl.image_url) + '" alt="">';
    return '<span class="ph playlist"><i class="fa-solid fa-list-ul"></i></span>';
  }

  function filtered(q) {
    var typeLabel = { video: 'Video', audio: 'Lyd', image: 'Billede' };
    var byGroup = { video: [], audio: [], image: [], playlists: [] };
    items.forEach(function (it) {
      if (!byGroup[it.type]) return;
      if (matches((it.title || '') + ' ' + (it.description || ''), q)) {
        byGroup[it.type].push('<button type="button" class="mp-row" data-kind="item" data-id="' + it.id + '">' +
          '<span class="mp-thumb">' + thumbFor(it) + '</span>' +
          '<span class="mp-meta"><span class="ttl">' + esc(it.title) + '</span><span class="sub">' + (typeLabel[it.type] || '') + '</span></span>' +
          '</button>');
      }
    });
    playlists.forEach(function (pl) {
      var hay = (pl.name || '') + ' ' + (pl.description || '') + ' ' + (pl.tracks || []).map(function (t) { return t.title || ''; }).join(' ');
      if (matches(hay, q)) {
        byGroup.playlists.push('<button type="button" class="mp-row" data-kind="playlist" data-id="' + pl.id + '">' +
          '<span class="mp-thumb">' + plThumb(pl) + '</span>' +
          '<span class="mp-meta"><span class="ttl">' + esc(pl.name) + '</span><span class="sub">Playliste · ' + pl.count + ' medier</span></span>' +
          '</button>');
      }
    });
    return byGroup;
  }

  function render() {
    var q = (search.value || '').toLowerCase().trim();
    var byGroup = filtered(q);

    // Nav: group links with counts; empty groups hidden. Search ignores the filter.
    var navHtml = '';
    var lead = true;
    GROUPS.forEach(function (g) {
      var n = byGroup[g.key].length;
      if (!n) return;
      navHtml += '<a href="#" data-group="' + g.key + '" class="' + (lead ? 'lead ' : '') + (q === '' && filter === g.key ? 'active' : '') + '">' + g.label + ' (' + n + ')</a>';
      lead = false;
    });
    nav.innerHTML = navHtml;

    var bodyHtml = '';
    GROUPS.forEach(function (g) {
      if (q === '' && filter && filter !== g.key) return;
      if (!byGroup[g.key].length) return;
      bodyHtml += '<div class="mp-sec">' + g.label + '</div>' + byGroup[g.key].join('');
    });
    body.innerHTML = bodyHtml || '<div class="mp-empty">' + ((items.length || playlists.length) ? 'Ingen resultater.' : 'Mediebiblioteket er tomt.') + '</div>';
  }

  async function load() {
    body.innerHTML = '<div class="mp-empty">Indlæser…</div>';
    nav.innerHTML = '';
    try {
      var res = await fetch(LIB_URL, { headers: { Accept: 'application/json' }});
      if (!res.ok) throw new Error('fetch failed');
      var data = await res.json();
      items = data.items || [];
      playlists = data.playlists || [];
      // Open sorted on Video by default (first non-empty group if no videos).
      var byGroup = filtered('');
      filter = GROUPS.map(function (g) { return g.key; }).find(function (k) { return byGroup[k].length; }) || null;
      render();
    } catch (err) {
      body.innerHTML = '<div class="mp-empty">Kunne ikke hente mediebiblioteket.</div>';
    }
  }

  function close() { backdrop.classList.remove('open'); onPick = null; }
  function open(cb) {
    onPick = cb;
    filter = null;
    search.value = '';
    backdrop.classList.add('open');
    load();
  }

  nav.addEventListener('click', function (e) {
    var a = e.target.closest('a[data-group]');
    if (!a) return;
    e.preventDefault();
    filter = (filter === a.dataset.group) ? null : a.dataset.group;
    render();
  });
  search.addEventListener('input', function () {
    if (search.value.trim() !== '') filter = null;
    render();
  });
  body.addEventListener('click', function (e) {
    var row = e.target.closest('.mp-row');
    if (!row || !onPick) return;
    var cb = onPick;
    var sel = null;
    if (row.dataset.kind === 'playlist') {
      var pl = playlists.find(function (x) { return String(x.id) === row.dataset.id; });
      if (pl) sel = { kind: 'playlist', playlist: pl };
    } else {
      var it = items.find(function (x) { return String(x.id) === row.dataset.id; });
      if (it) sel = { kind: 'item', item: it };
    }
    if (!sel) return;
    close();
    cb(sel);
  });
  document.getElementById('mpClose').addEventListener('click', close);
  backdrop.addEventListener('click', function (e) { if (e.target === backdrop) close(); });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && backdrop.classList.contains('open')) close();
  });

  return { open: open };
})();
</script>
@endpush
@endonce
