@auth
<div class="feed-actions">
  <a href="{{ url('/chat') }}" class="feed-iconbtn" title="Beskeder" aria-label="Beskeder">
    <i class="fa-regular fa-envelope"></i>
    <span class="badge" id="hdrMessagesBadge">0</span>
  </a>
  <div class="notif-wrap">
    <button type="button" class="feed-iconbtn" id="hdrNotifBtn" title="Notifikationer" aria-label="Notifikationer" aria-expanded="false">
      <i class="fa-regular fa-bell"></i>
      <span class="badge" id="hdrNotifBadge">0</span>
    </button>
    <div class="notif-dropdown" id="notifDropdown" role="menu">
      <div class="notif-dropdown-head">
        <span>Notifikationer</span>
        <button type="button" class="btn btn-ghost btn-sm" id="markAllReadBtn" style="font-size:12px;">Markér alle som læst</button>
      </div>
      <div class="notif-dropdown-body" id="alertsList">
        <div class="alerts-empty">Indlæser…</div>
      </div>
    </div>
  </div>
</div>

@push('scripts')
<script>
(function () {
  var CSRF = document.querySelector('meta[name=csrf-token]').content;
  var notifBtn = document.getElementById('hdrNotifBtn');
  var notifDropdown = document.getElementById('notifDropdown');
  var notifBadge = document.getElementById('hdrNotifBadge');
  var msgBadge = document.getElementById('hdrMessagesBadge');
  var alertsList = document.getElementById('alertsList');
  var markBtn = document.getElementById('markAllReadBtn');
  if (!notifBtn) return;

  function setBadge(el, n) {
    if (!el) return;
    if (n > 0) { el.textContent = n > 99 ? '99+' : n; el.classList.add('show'); }
    else el.classList.remove('show');
  }
  function escapeHtml(s) { return String(s ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }
  var ICON_FOR_TYPE = { message: 'fa-comment-dots', enrollment: 'fa-user-plus', broadcast: 'fa-envelope', system: 'fa-circle-info' };
  var COLOR_FOR_TYPE = { message: '#1877f2', enrollment: '#16a34a', broadcast: '#8b5cf6', system: '#65676b' };

  async function loadList() {
    try {
      var res = await fetch('{{ url("/api/notifications") }}', { headers: { Accept: 'application/json' }});
      var data = await res.json();
      if (!data.notifications.length) { alertsList.innerHTML = '<div class="alerts-empty">Ingen notifikationer endnu.</div>'; return; }
      alertsList.innerHTML = data.notifications.map(function (n) {
        var avatar = n.actor && n.actor.picture_url
          ? '<div class="av sm"><img src="' + escapeHtml(n.actor.picture_url) + '"></div>'
          : '<div class="av sm">' + escapeHtml((n.actor && n.actor.initials) || '?') + '</div>';
        var icon = '<span class="alert-icon-overlay"><i class="fa-solid ' + (ICON_FOR_TYPE[n.type] || 'fa-circle') + '" style="color:' + (COLOR_FOR_TYPE[n.type] || '#65676b') + ';"></i></span>';
        var body = '<strong>' + escapeHtml(n.title) + '</strong>' + (n.body ? '<div class="snippet">' + escapeHtml(n.body) + '</div>' : '') + '<div class="alert-time">' + escapeHtml(n.time_human) + '</div>';
        return '<a class="alert-item' + (n.read ? '' : ' unread') + '" href="' + (n.link || '#') + '"><div class="alert-avatar-wrap">' + avatar + icon + '</div><div class="alert-body">' + body + '</div></a>';
      }).join('');
    } catch (e) { alertsList.innerHTML = '<div class="alerts-empty">Kunne ikke hente.</div>'; }
  }

  notifBtn.addEventListener('click', async function (e) {
    e.stopPropagation();
    var wasOpen = notifDropdown.classList.contains('open');
    notifDropdown.classList.toggle('open');
    notifBtn.setAttribute('aria-expanded', !wasOpen);
    if (!wasOpen) {
      await loadList();
      try {
        await fetch('{{ url("/api/notifications/read-all") }}', { method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF, Accept: 'application/json' }});
        setBadge(notifBadge, 0);
      } catch {}
    }
  });
  if (markBtn) markBtn.addEventListener('click', async function (e) {
    e.preventDefault(); e.stopPropagation();
    try {
      await fetch('{{ url("/api/notifications/read-all") }}', { method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF, Accept: 'application/json' }});
      setBadge(notifBadge, 0);
      await loadList();
    } catch {}
  });
  document.addEventListener('click', function (e) {
    if (!notifDropdown.classList.contains('open')) return;
    if (notifDropdown.contains(e.target) || notifBtn.contains(e.target)) return;
    notifDropdown.classList.remove('open');
    notifBtn.setAttribute('aria-expanded', 'false');
  });

  async function pollCounts() {
    try {
      var res = await fetch('{{ url("/api/notifications/counts") }}');
      var data = await res.json();
      setBadge(notifBadge, data.notifications || 0);
      setBadge(msgBadge, data.messages || 0);
    } catch {}
  }
  pollCounts();
  setInterval(pollCounts, 8000);
})();
</script>
@endpush
@endauth
