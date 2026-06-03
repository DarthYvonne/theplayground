{{-- Styled audio player. Drop a `<div class="tp-audio" data-src="…"></div>`
     anywhere and call tpAudio.init(root) — the markup is built by JS so
     server-rendered pages and JS-rendered feeds share one implementation. --}}
@once
@push('styles')
<style>
  .tp-audio { display: flex; align-items: center; gap: 12px; background: #fff; border: 1px solid var(--border); border-radius: 999px; padding: 7px 16px 7px 7px; }
  .tp-audio .pa-btn { width: 38px; height: 38px; border-radius: 50%; border: none; background: var(--accent); color: #fff; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; font-size: 14px; flex: 0 0 auto; transition: background 0.1s; }
  .tp-audio .pa-btn:hover { background: var(--accent-hover); }
  .tp-audio .pa-btn i { margin-left: 1px; }
  .tp-audio .pa-btn i.fa-pause { margin-left: 0; }
  .tp-audio .pa-track { flex: 1; height: 6px; background: #e4e6eb; border-radius: 3px; cursor: pointer; position: relative; min-width: 40px; }
  .tp-audio .pa-track::before { content: ""; position: absolute; inset: -8px 0; } /* bigger touch target */
  .tp-audio .pa-progress { height: 100%; width: 0; background: var(--accent); border-radius: 3px; pointer-events: none; }
  .tp-audio .pa-time { font-size: 12px; color: var(--muted); font-variant-numeric: tabular-nums; flex: 0 0 auto; }
  /* Compact variant for small cards */
  .tp-audio.sm { gap: 8px; padding: 5px 10px 5px 5px; }
  .tp-audio.sm .pa-btn { width: 30px; height: 30px; font-size: 12px; }
  .tp-audio.sm .pa-time { font-size: 11px; }
</style>
@endpush
@push('scripts')
<script>
window.tpAudio = (function () {
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
      '<div class="pa-track"><div class="pa-progress"></div></div>' +
      '<span class="pa-time">0:00</span>';
    var audio = new Audio();
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
      // One at a time — pause every other player on the page.
      document.querySelectorAll('.tp-audio').forEach(function (other) {
        if (other !== el && other._audio && !other._audio.paused) other._audio.pause();
      });
      icon.className = 'fa-solid fa-pause';
      btn.setAttribute('aria-label', 'Pause');
    });
    audio.addEventListener('pause', function () {
      icon.className = 'fa-solid fa-play';
      btn.setAttribute('aria-label', 'Afspil');
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
  return { init: init, markup: markup };
})();
</script>
@endpush
@endonce
