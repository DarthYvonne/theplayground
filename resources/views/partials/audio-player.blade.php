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
  function init(root) {
    (root || document).querySelectorAll('.tp-audio').forEach(build);
  }
  function markup(src, extraClass) {
    return '<div class="tp-audio ' + (extraClass || '') + '" data-src="' + String(src).replace(/"/g, '&quot;') + '"></div>';
  }
  return { init: init, markup: markup, register: register, pauseOthers: pauseOthers };
})();
</script>
@endpush
@endonce
