{{-- Styled audio player. Drop a `<div class="tp-audio" data-src="…"></div>`
     anywhere and call tpAudio.init(root) — the markup is built by JS so
     server-rendered pages and JS-rendered feeds share one implementation. --}}
@once
@push('styles')
<style>
  .tp-audio { display: flex; align-items: center; gap: 12px; background: linear-gradient(135deg, #f6faff 0%, #e7f0fe 100%); border: 1px solid #d6e4fa; border-radius: 16px; padding: 10px 16px 10px 10px; box-shadow: 0 1px 3px rgba(24,119,242,0.08); }
  .tp-audio .pa-btn { width: 42px; height: 42px; border-radius: 50%; border: none; background: linear-gradient(135deg, #4d97ff 0%, #1664d8 100%); color: #fff; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; font-size: 15px; flex: 0 0 auto; box-shadow: 0 3px 10px rgba(24,119,242,0.35); transition: transform 0.12s, box-shadow 0.12s; }
  .tp-audio .pa-btn:hover { transform: scale(1.07); box-shadow: 0 5px 14px rgba(24,119,242,0.45); }
  .tp-audio .pa-btn:active { transform: scale(0.96); }
  .tp-audio .pa-btn i { margin-left: 2px; }
  .tp-audio .pa-btn i.fa-pause { margin-left: 0; }
  .tp-audio .pa-eq { display: flex; align-items: flex-end; gap: 2px; height: 16px; flex: 0 0 auto; opacity: 0.3; }
  .tp-audio .pa-eq span { width: 3px; height: 30%; background: var(--accent); border-radius: 2px; }
  .tp-audio.playing .pa-eq { opacity: 1; }
  .tp-audio.playing .pa-eq span { animation: tp-eq 0.9s ease-in-out infinite; }
  .tp-audio.playing .pa-eq span:nth-child(2) { animation-delay: 0.25s; }
  .tp-audio.playing .pa-eq span:nth-child(3) { animation-delay: 0.5s; }
  @keyframes tp-eq { 0%, 100% { height: 30%; } 50% { height: 100%; } }
  .tp-audio .pa-track { flex: 1; height: 8px; background: rgba(24,119,242,0.14); border-radius: 4px; cursor: pointer; position: relative; min-width: 40px; }
  .tp-audio .pa-track::before { content: ""; position: absolute; inset: -8px 0; } /* bigger touch target */
  .tp-audio .pa-progress { height: 100%; width: 0; background: linear-gradient(90deg, #4d97ff, #1877f2); border-radius: 4px; pointer-events: none; position: relative; }
  .tp-audio .pa-progress::after { content: ""; position: absolute; right: -6px; top: 50%; transform: translateY(-50%); width: 14px; height: 14px; border-radius: 50%; background: #fff; border: 3px solid var(--accent); box-shadow: 0 1px 4px rgba(0,0,0,0.25); opacity: 0; transition: opacity 0.12s; }
  .tp-audio:hover .pa-progress::after, .tp-audio.playing .pa-progress::after { opacity: 1; }
  .tp-audio .pa-time { font-size: 12px; color: var(--muted); font-variant-numeric: tabular-nums; flex: 0 0 auto; font-weight: 600; }
  /* Compact variant for small cards */
  .tp-audio.sm { gap: 8px; padding: 6px 10px 6px 6px; border-radius: 12px; }
  .tp-audio.sm .pa-btn { width: 30px; height: 30px; font-size: 12px; }
  .tp-audio.sm .pa-eq { display: none; }
  .tp-audio.sm .pa-time { font-size: 11px; }

  /* Shared playlist player — used when a playlist is shared in the feed or on a Hold */
  .tp-playlist { background: #fff; border: 1px solid var(--border); border-radius: 12px; overflow: hidden; }
  .tp-playlist .tpl-cover { display: block; width: 100%; height: 120px; object-fit: cover; background: #f0f2f5; }
  .tp-playlist .tpl-head { display: flex; align-items: center; gap: 10px; padding: 10px 14px; border-bottom: 1px solid #f0f2f5; }
  .tp-playlist .tpl-play { width: 36px; height: 36px; border: none; border-radius: 50%; background: linear-gradient(135deg, #4d97ff 0%, #1664d8 100%); color: #fff; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; font-size: 13px; flex: 0 0 auto; box-shadow: 0 2px 8px rgba(24,119,242,0.35); transition: transform 0.12s; }
  .tp-playlist .tpl-play:hover { transform: scale(1.07); }
  .tp-playlist .tpl-play i { margin-left: 2px; }
  .tp-playlist .tpl-play i.fa-pause { margin-left: 0; }
  .tp-playlist .tpl-name { font-weight: 700; font-size: 14px; flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .tp-playlist .tpl-name .cnt { color: var(--muted); font-weight: 600; }
  .tp-playlist .tpl-tracks { display: flex; flex-direction: column; padding: 6px; }
  .tp-playlist .tpl-track { display: flex; align-items: center; gap: 10px; padding: 8px 10px; border: none; background: none; border-radius: 8px; font: inherit; text-align: left; cursor: pointer; color: var(--text); width: 100%; text-decoration: none; }
  .tp-playlist .tpl-track:hover { background: var(--hover); }
  .tp-playlist .tpl-track + .tpl-track { border-top: 1px solid #f0f2f5; border-radius: 0; }
  .tp-playlist .tpl-track .ticon { width: 26px; height: 26px; border-radius: 50%; background: var(--accent-soft); color: var(--accent); display: inline-flex; align-items: center; justify-content: center; font-size: 10px; flex: 0 0 auto; }
  .tp-playlist .tpl-track .tthumb { width: 42px; height: 26px; border-radius: 6px; object-fit: cover; flex: 0 0 auto; background: #f0f2f5; display: block; }
  .tp-playlist .tpl-track .t { flex: 1; min-width: 0; font-size: 13px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .tp-playlist .tpl-track.playing { background: var(--accent-soft); }
  .tp-playlist .tpl-track.playing .ticon { background: var(--accent); color: #fff; }
  .tp-playlist .tpl-player { display: none; align-items: center; gap: 10px; padding: 10px 14px; border-top: 1px solid #f0f2f5; }
  .tp-playlist .tpl-player.on { display: flex; }
  .tp-playlist .tpl-bar { flex: 1; height: 6px; background: rgba(24,119,242,0.14); border-radius: 3px; cursor: pointer; position: relative; }
  .tp-playlist .tpl-bar::before { content: ""; position: absolute; inset: -8px 0; }
  .tp-playlist .tpl-prog { height: 100%; width: 0; background: linear-gradient(90deg, #4d97ff, #1877f2); border-radius: 3px; pointer-events: none; }
  .tp-playlist .tpl-time { font-size: 11px; color: var(--muted); font-variant-numeric: tabular-nums; font-weight: 600; flex: 0 0 auto; }
</style>
@endpush
@push('scripts')
<script>
window.tpAudio = (function () {
  // Every playing audio on the page — tp-audio players and playlist cards —
  // registers here so starting one pauses the rest.
  var registry = [];
  function register(a) { if (registry.indexOf(a) === -1) registry.push(a); return a; }
  function pauseOthers(a) { registry.forEach(function (x) { if (x !== a && !x.paused) x.pause(); }); }

  function fmt(s) {
    if (!isFinite(s) || s < 0) return '0:00';
    s = Math.floor(s);
    var m = Math.floor(s / 60), r = s % 60;
    return m + ':' + (r < 10 ? '0' : '') + r;
  }
  function build(el) {
    if (el.dataset.ready) return;
    el.dataset.ready = '1';
    el.innerHTML =
      '<button type="button" class="pa-btn" aria-label="Afspil"><i class="fa-solid fa-play"></i></button>' +
      '<span class="pa-eq"><span></span><span></span><span></span></span>' +
      '<div class="pa-track"><div class="pa-progress"></div></div>' +
      '<span class="pa-time">0:00</span>';
    var audio = register(new Audio());
    audio.preload = 'metadata';
    audio.src = el.dataset.src;
    el._audio = audio;
    var btn = el.querySelector('.pa-btn');
    var icon = btn.querySelector('i');
    var track = el.querySelector('.pa-track');
    var prog = el.querySelector('.pa-progress');
    var time = el.querySelector('.pa-time');

    audio.addEventListener('loadedmetadata', function () { time.textContent = '0:00 / ' + fmt(audio.duration); });
    audio.addEventListener('timeupdate', function () {
      if (audio.duration) prog.style.width = (audio.currentTime / audio.duration * 100) + '%';
      time.textContent = fmt(audio.currentTime) + (isFinite(audio.duration) ? ' / ' + fmt(audio.duration) : '');
    });
    audio.addEventListener('play', function () {
      // One at a time — pause every other registered player on the page.
      pauseOthers(audio);
      icon.className = 'fa-solid fa-pause';
      btn.setAttribute('aria-label', 'Pause');
      el.classList.add('playing');
    });
    audio.addEventListener('pause', function () {
      icon.className = 'fa-solid fa-play';
      btn.setAttribute('aria-label', 'Afspil');
      el.classList.remove('playing');
    });
    audio.addEventListener('ended', function () { audio.currentTime = 0; });

    btn.addEventListener('click', function () { audio.paused ? audio.play() : audio.pause(); });
    track.addEventListener('click', function (e) {
      if (!audio.duration) return;
      var r = track.getBoundingClientRect();
      audio.currentTime = Math.min(Math.max((e.clientX - r.left) / r.width, 0), 1) * audio.duration;
    });
  }
  function esc(s) { return String(s ?? '').replace(/[&<>"']/g, function (m) { return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[m]; }); }

  // ---- Playlist player: play all (auto-advance) or any single entry ----
  function buildPlaylist(el) {
    if (el.dataset.ready) return;
    el.dataset.ready = '1';
    var pl;
    try { pl = JSON.parse(el.dataset.playlist); } catch (_) { return; }
    var tracks = (pl.tracks || []).filter(function (t) { return t.url; });
    var audioTracks = tracks.filter(function (t) { return t.type === 'audio'; });

    var html = '';
    if (pl.image_url) html += '<img class="tpl-cover" src="' + esc(pl.image_url) + '" alt="" loading="lazy">';
    html += '<div class="tpl-head">' +
      (audioTracks.length ? '<button type="button" class="tpl-play" aria-label="Afspil alle" title="Afspil alle"><i class="fa-solid fa-play"></i></button>' : '') +
      '<div class="tpl-name">' + esc(pl.name) + ' <span class="cnt">(' + tracks.length + ')</span></div>' +
      '</div>';
    html += '<div class="tpl-tracks">' + tracks.map(function (t, i) {
      var thumb = (t.type === 'video' && t.thumbnail_url) ? '<img class="tthumb" src="' + esc(t.thumbnail_url) + '" alt="" loading="lazy">'
        : (t.type === 'image' ? '<img class="tthumb" src="' + esc(t.url) + '" alt="" loading="lazy">'
        : '<span class="ticon"><i class="fa-solid ' + (t.type === 'audio' ? 'fa-play' : 'fa-film') + '"></i></span>');
      if (t.type === 'audio') {
        return '<button type="button" class="tpl-track" data-i="' + i + '">' + thumb + '<span class="t">' + esc(t.title) + '</span></button>';
      }
      // Video/image entries open in a new tab — playable queue is audio-only.
      return '<a class="tpl-track" href="' + esc(t.url) + '" target="_blank" rel="noopener">' + thumb + '<span class="t">' + esc(t.title) + '</span></a>';
    }).join('') + '</div>';
    html += '<div class="tpl-player"><div class="tpl-bar"><div class="tpl-prog"></div></div><span class="tpl-time">0:00</span></div>';
    el.innerHTML = html;

    if (!audioTracks.length) return;

    var rows = Array.prototype.slice.call(el.querySelectorAll('button.tpl-track[data-i]'));
    var audio = register(new Audio());
    audio.preload = 'none';
    var playBtn = el.querySelector('.tpl-play');
    var player = el.querySelector('.tpl-player');
    var bar = el.querySelector('.tpl-bar');
    var prog = el.querySelector('.tpl-prog');
    var time = el.querySelector('.tpl-time');
    var current = -1; // index into rows/audioRows order
    var queue = false;
    var srcs = rows.map(function (r) { return tracks[parseInt(r.dataset.i, 10)].url; });

    function refresh() {
      rows.forEach(function (r, j) {
        var isCur = j === current;
        r.classList.toggle('playing', isCur);
        r.querySelector('.ticon i').className = 'fa-solid ' + (isCur && !audio.paused ? 'fa-pause' : 'fa-play');
      });
      if (playBtn) {
        var pausing = !audio.paused && queue;
        playBtn.innerHTML = pausing ? '<i class="fa-solid fa-pause"></i>' : '<i class="fa-solid fa-play"></i>';
      }
    }
    function playIndex(i) {
      current = i;
      audio.src = srcs[i];
      audio.play();
      player.classList.add('on');
      prog.style.width = '0%';
    }
    rows.forEach(function (r, i) {
      r.addEventListener('click', function () {
        if (current === i) { audio.paused ? audio.play() : audio.pause(); }
        else { playIndex(i); }
      });
    });
    if (playBtn) playBtn.addEventListener('click', function () {
      if (queue && current !== -1) { audio.paused ? audio.play() : audio.pause(); }
      else { queue = true; playIndex(0); }
    });
    audio.addEventListener('play', function () { pauseOthers(audio); refresh(); });
    audio.addEventListener('pause', refresh);
    audio.addEventListener('ended', function () {
      if (queue && current + 1 < rows.length) playIndex(current + 1);
      else { audio.currentTime = 0; queue = false; refresh(); }
    });
    audio.addEventListener('loadedmetadata', function () { time.textContent = '0:00 / ' + fmt(audio.duration); });
    audio.addEventListener('timeupdate', function () {
      if (audio.duration) prog.style.width = (audio.currentTime / audio.duration * 100) + '%';
      time.textContent = fmt(audio.currentTime) + (isFinite(audio.duration) ? ' / ' + fmt(audio.duration) : '');
    });
    bar.addEventListener('click', function (e) {
      if (!audio.duration) return;
      var r = bar.getBoundingClientRect();
      audio.currentTime = Math.min(Math.max((e.clientX - r.left) / r.width, 0), 1) * audio.duration;
    });
  }

  function init(root) {
    (root || document).querySelectorAll('.tp-audio').forEach(build);
    (root || document).querySelectorAll('.tp-playlist[data-playlist]').forEach(buildPlaylist);
  }
  function markup(src, extraClass) {
    return '<div class="tp-audio ' + (extraClass || '') + '" data-src="' + String(src).replace(/"/g, '&quot;') + '"></div>';
  }
  function playlistMarkup(pl) {
    return '<div class="tp-playlist" data-playlist="' + esc(JSON.stringify(pl)) + '"></div>';
  }
  return { init: init, markup: markup, playlistMarkup: playlistMarkup, register: register, pauseOthers: pauseOthers };
})();
</script>
@endpush
@endonce
