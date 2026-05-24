<!DOCTYPE html>
<html lang="da">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $title ?? 'The Playground' }}</title>
<link rel="icon" type="image/png" href="{{ asset('img/playground_logo.png') }}">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

<style>
  :root {
    --accent: #1877f2;
    --accent-hover: #1664d8;
    --accent-soft: #e7f0fe;
    --danger: #e11d48;
    --success: #16a34a;
    --muted: #65676b;
    --text: #1c1e21;
    --bg: #f0f2f5;
    --card: #fff;
    --border: #dadde1;
    --hover: #f0f2f5;
    --bubble: #f0f2f5;
  }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  html, body { height: 100%; }
  body { font-family: -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background: var(--bg); color: var(--text); font-size: 14px; -webkit-font-smoothing: antialiased; }
  a { color: inherit; text-decoration: none; }
  img { max-width: 100%; }

  /* App shell */
  .app { display: grid; grid-template-columns: 260px 1fr; min-height: 100vh; }
  .sidebar { background: #fff; border-right: 1px solid var(--border); padding: 16px 12px; position: sticky; top: 0; height: 100vh; display: flex; flex-direction: column; }
  .mobile-topbar { display: none; }
  .sidebar-backdrop { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.45); z-index: 1001; }
  .sidebar-backdrop.open { display: block; }
  .main { padding: 22px 26px 40px; min-width: 0; max-width: 100%; overflow-x: hidden; }
  /* Uniform content column — wide enough for 2 hold-tiles per row, left-aligned */
  .main > * { width: 100%; max-width: 720px; margin-left: 0; margin-right: auto; }

  /* Sidebar */
  .logo { padding: 6px 8px 18px; }
  .logo img { width: 100%; max-width: 200px; height: auto; display: block; }
  .nav { flex: 1; }
  .nav a { display: flex; align-items: center; gap: 12px; padding: 10px 12px; border-radius: 8px; margin-bottom: 2px; cursor: pointer; color: var(--text); font-weight: 500; }
  .nav a:hover { background: var(--hover); }
  .nav a.active { background: var(--accent-soft); color: var(--accent); font-weight: 600; }
  .nav .ico { width: 22px; display: inline-block; text-align: center; color: var(--muted); }
  .nav a.active .ico { color: var(--accent); }
  .nav .badge-pill { margin-left: auto; background: var(--danger); color: #fff; font-size: 11px; font-weight: 700; padding: 1px 7px; border-radius: 10px; min-width: 20px; text-align: center; }
  .nav .count-pill { background: #1c1e21; color: #fff; font-size: 11px; font-weight: 700; padding: 1px 7px; border-radius: 10px; min-width: 20px; text-align: center; margin-left: 6px; }
  .nav-section { font-size: 11px; text-transform: uppercase; letter-spacing: 0.4px; color: var(--muted); padding: 16px 12px 6px; }
  .logout-form { margin-top: 2px; }
  .logout-form button { width: 100%; padding: 10px 12px; background: none; border: none; color: var(--muted); cursor: pointer; font-weight: 600; font-size: 14px; text-align: left; border-radius: 8px; display: flex; align-items: center; gap: 12px; font-family: inherit; }
  .logout-form button:hover { background: var(--hover); color: var(--danger); }

  /* Sidebar profile (avatar + first name above Log ud) */
  .sidebar-profile-wrap { margin-top: 8px; border-top: 1px solid #f0f2f5; padding-top: 8px; }
  .sidebar-profile { display: flex; align-items: center; gap: 10px; padding: 8px 10px; border-radius: 8px; color: var(--text); font-weight: 600; }
  .sidebar-profile:hover { background: var(--hover); }
  .sidebar-profile.active { background: var(--accent-soft); color: var(--accent); }
  .sidebar-profile .av { flex-shrink: 0; }

  /* Preview role switcher (owner only) */
  .preview-role-form { margin-top: 8px; border-top: 1px solid #f0f2f5; padding: 10px 12px 4px; }
  .preview-role-form .lbl { display: block; font-size: 11px; text-transform: uppercase; letter-spacing: 0.4px; color: var(--muted); font-weight: 700; margin-bottom: 6px; }
  .preview-role-form select { width: 100%; padding: 8px 10px; font-size: 13px; }
  .preview-banner { background: #fef3c7; color: #92400e; border: 1px solid #fcd34d; border-radius: 8px; padding: 8px 12px; margin-bottom: 14px; font-size: 13px; display: flex; align-items: center; gap: 8px; }
  .preview-banner .stop { margin-left: auto; }
  .preview-banner .stop button { background: none; border: none; color: inherit; text-decoration: underline; cursor: pointer; font-size: 13px; font-family: inherit; font-weight: 600; padding: 0; }

  /* View header */
  .view-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; flex-wrap: wrap; gap: 10px; }
  .view-header h1 { font-size: 22px; font-weight: 700; }
  .view-header h1 small { display: block; font-size: 12px; font-weight: 400; color: var(--muted); margin-top: 2px; }

  /* Buttons */
  .btn { display: inline-flex; align-items: center; gap: 8px; padding: 9px 16px; border-radius: 8px; border: none; cursor: pointer; font-weight: 600; font-size: 14px; font-family: inherit; text-decoration: none; line-height: 1; transition: background 0.1s; }
  .btn-primary { background: var(--accent); color: #fff; }
  .btn-primary:hover { background: var(--accent-hover); }
  .btn-secondary { background: #e4e6eb; color: var(--text); }
  .btn-secondary:hover { background: #d8dadf; }
  .btn-danger { background: #fee2e2; color: #b91c1c; }
  .btn-danger:hover { background: #fecaca; }
  .btn-ghost { background: transparent; color: var(--text); }
  .btn-ghost:hover { background: var(--hover); }
  .btn-sm { padding: 6px 12px; font-size: 13px; }
  .btn:disabled { opacity: 0.5; cursor: not-allowed; }

  /* Cards */
  .card { background: var(--card); border-radius: 12px; box-shadow: 0 1px 2px rgba(0,0,0,0.08); margin-bottom: 16px; overflow: hidden; }
  .card-pad { padding: 16px 18px; }

  /* Course tile (uniform "hold" card) */
  .course-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 14px; }
  .course-tile { margin-bottom: 0; display: flex; flex-direction: column; }
  .course-tile-img { width: 100%; height: 140px; object-fit: cover; display: block; }
  .course-tile-img-ph { background: linear-gradient(135deg, var(--accent-soft), #f5f7fb); display: flex; align-items: center; justify-content: center; color: var(--accent); font-size: 40px; }
  .course-tile-title { font-weight: 700; }
  .course-tile-meta { color: var(--muted); font-size: 13px; margin-top: 2px; }
  .course-tile-actions { display: flex; gap: 6px; margin-top: 12px; flex-wrap: wrap; }

  /* Avatar */
  .av { width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #a1c4fd, #c2e9fb); display: inline-flex; align-items: center; justify-content: center; font-weight: 700; color: #fff; font-size: 14px; overflow: hidden; flex-shrink: 0; }
  .av img { width: 100%; height: 100%; object-fit: cover; }
  .av.sm { width: 32px; height: 32px; font-size: 12px; }
  .av.lg { width: 56px; height: 56px; font-size: 18px; }
  .av.xl { width: 88px; height: 88px; font-size: 26px; }

  /* Forms */
  label { font-size: 13px; font-weight: 600; color: var(--text); display: block; margin-bottom: 6px; }
  input[type=text], input[type=email], input[type=password], input[type=number], input[type=tel], input[type=time], input[type=date], input[type=file], select, textarea {
    width: 100%; font-family: inherit; font-size: 14px; padding: 10px 12px; border: 1px solid var(--border); border-radius: 8px; background: #fff; color: var(--text);
  }
  input:focus, select:focus, textarea:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px rgba(24,119,242,0.15); }
  textarea { resize: vertical; min-height: 100px; }
  .form-row { margin-bottom: 14px; }
  .form-row .hint { font-size: 12px; color: var(--muted); margin-top: 4px; }
  .form-row .err { font-size: 12px; color: var(--danger); margin-top: 4px; }
  .switch { display: inline-flex; align-items: center; gap: 8px; cursor: pointer; user-select: none; }
  .switch input { display: none; }
  .switch .knob { width: 38px; height: 22px; background: #ccd0d5; border-radius: 999px; position: relative; transition: background 0.15s; }
  .switch .knob::after { content: ''; position: absolute; top: 2px; left: 2px; width: 18px; height: 18px; background: #fff; border-radius: 50%; transition: left 0.15s; box-shadow: 0 1px 3px rgba(0,0,0,0.15); }
  .switch input:checked + .knob { background: var(--accent); }
  .switch input:checked + .knob::after { left: 18px; }

  /* Alerts */
  .alert { padding: 10px 14px; border-radius: 8px; margin-bottom: 14px; font-size: 13px; display: flex; gap: 8px; align-items: center; }
  .alert-success { background: #dcfce7; color: #166534; }
  .alert-error { background: #fee2e2; color: #b91c1c; }
  .alert-info { background: var(--accent-soft); color: var(--accent); }

  /* Tag/pill */
  .tag { display: inline-flex; align-items: center; gap: 4px; background: var(--accent-soft); color: var(--accent); font-size: 12px; padding: 3px 10px; border-radius: 12px; font-weight: 600; }
  .tag.muted { background: #f0f2f5; color: var(--muted); }
  .tag.danger { background: #fee2e2; color: #b91c1c; }
  .tag.success { background: #dcfce7; color: #166534; }
  .tag.outline-danger { background: transparent; color: #b91c1c; border: 1px solid #fca5a5; }

  /* Header notification/inbox buttons */
  .feed-actions { display: flex; gap: 8px; align-items: center; }
  .feed-iconbtn { position: relative; background: #e4e6eb; color: var(--text); border: none; width: 40px; height: 40px; border-radius: 50%; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; font-size: 17px; text-decoration: none; flex-shrink: 0; }
  .feed-iconbtn:hover { background: #d8dadf; }
  .feed-iconbtn .badge { position: absolute; top: -2px; right: -2px; background: var(--danger); color: #fff; font-size: 11px; font-weight: 700; padding: 1px 6px; border-radius: 10px; min-width: 18px; line-height: 1.3; text-align: center; border: 2px solid var(--bg); display: none; }
  .feed-iconbtn .badge.show { display: inline-block; }

  /* Notif dropdown */
  .notif-wrap { position: relative; }
  .notif-dropdown { position: absolute; top: calc(100% + 8px); right: 0; width: 380px; max-width: calc(100vw - 24px); max-height: 70vh; background: #fff; border-radius: 12px; box-shadow: 0 8px 32px rgba(0,0,0,0.18); z-index: 9997; display: none; flex-direction: column; overflow: hidden; }
  .notif-dropdown.open { display: flex; }
  .notif-dropdown-head { padding: 12px 16px; border-bottom: 1px solid #f0f2f5; font-weight: 700; font-size: 16px; display: flex; align-items: center; justify-content: space-between; }
  .notif-dropdown-body { overflow-y: auto; flex: 1; padding: 6px; }
  .alert-item { display: flex; gap: 10px; padding: 10px; border-radius: 8px; text-decoration: none; color: var(--text); }
  .alert-item + .alert-item { border-top: 1px solid #f0f2f5; }
  .alert-item:hover { background: var(--hover); }
  .alert-item.unread { background: var(--accent-soft); }
  .alert-avatar-wrap { position: relative; flex-shrink: 0; }
  .alert-icon-overlay { position: absolute; bottom: -3px; right: -3px; width: 22px; height: 22px; border-radius: 50%; background: #fff; display: flex; align-items: center; justify-content: center; border: 2px solid #fff; box-shadow: 0 0 0 1px rgba(0,0,0,0.05); font-size: 11px; }
  .alert-body { flex: 1; min-width: 0; font-size: 13px; line-height: 1.4; }
  .alert-body strong { font-weight: 600; }
  .alert-body .snippet { color: var(--muted); font-size: 12px; margin-top: 2px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .alert-time { color: var(--muted); font-size: 11px; margin-top: 2px; }
  .alerts-empty { color: var(--muted); font-size: 13px; padding: 24px 16px; text-align: center; }

  /* Mobile */
  @media (max-width: 767px) {
    .app { grid-template-columns: 1fr; }
    .sidebar { position: fixed; left: -280px; top: 0; z-index: 1002; transition: left 0.25s ease; width: 260px; height: 100vh; overflow-y: auto; }
    .sidebar.open { left: 0; box-shadow: 4px 0 24px rgba(0,0,0,0.18); }
    .mobile-topbar { display: flex; align-items: center; position: fixed; top: 0; left: 0; right: 0; height: 56px; background: #fff; border-bottom: 1px solid var(--border); z-index: 1000; padding: 0 12px; gap: 10px; }
    .topbar-toggle { width: 38px; height: 38px; background: none; border: none; cursor: pointer; font-size: 18px; color: var(--text); display: flex; align-items: center; justify-content: center; border-radius: 8px; flex-shrink: 0; }
    .topbar-toggle:hover { background: var(--hover); }
    .topbar-title { font-size: 17px; font-weight: 700; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; flex: 1; }
    .topbar-actions { margin-left: auto; display: flex; gap: 6px; align-items: center; flex-shrink: 0; }
    .topbar-actions .feed-iconbtn { width: 38px; height: 38px; font-size: 16px; }
    .topbar-actions .feed-iconbtn .badge { border-color: #fff; }
    .topbar-actions .btn { padding: 7px 12px; font-size: 13px; }
    .main { padding: 70px 14px 24px; }
    .view-header { padding-left: 2px; }
    .view-header h1 { font-size: 18px; }
    .notif-dropdown { position: fixed; top: 60px; right: 8px; left: 8px; width: auto; max-width: none; }
  }
</style>
@stack('styles')
</head>
<body>

<header class="mobile-topbar">
  <button class="topbar-toggle" id="sidebarToggle" aria-label="Menu"><i class="fa-solid fa-bars"></i></button>
  <span class="topbar-title" id="topbarTitle">{{ $title ?? 'The Playground' }}</span>
  <div class="topbar-actions" id="topbarActions"></div>
</header>
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

<div class="app">
  <aside class="sidebar" id="sidebar">
    <div class="logo">
      <a href="{{ auth()->check() ? url('/dashboard') : url('/') }}">
        <img src="{{ asset('img/playground_logo.png') }}" alt="The Playground">
      </a>
    </div>
    <nav class="nav">
      @auth
        <a href="{{ url('/dashboard') }}" class="{{ request()->is('dashboard*') ? 'active' : '' }}"><span class="ico"><i class="fa-regular fa-newspaper"></i></span> Start</a>
        @php $myHoldCount = auth()->user()->activeEnrollments()->count(); @endphp
        <a href="{{ route('catalog.mine') }}" class="{{ request()->is('hold') || request()->is('hold/*') || request()->is('calendar') ? 'active' : '' }}"><span class="ico"><i class="fa-solid fa-dumbbell"></i></span> Hold @if ($myHoldCount > 0)<span class="count-pill">{{ $myHoldCount }}</span>@endif</a>
        @php $beskederUnread = auth()->user()->unreadDirectMessageCount(); @endphp
        <a href="{{ route('beskeder.index') }}" class="{{ request()->is('beskeder*') ? 'active' : '' }}"><span class="ico"><i class="fa-regular fa-envelope"></i></span> Beskeder @if ($beskederUnread > 0)<span class="badge-pill">{{ $beskederUnread > 99 ? '99+' : $beskederUnread }}</span>@endif</a>
        <a href="{{ url('/medlemmer') }}" class="{{ request()->is('medlemmer*') ? 'active' : '' }}"><span class="ico"><i class="fa-solid fa-users"></i></span> Medlemmer</a>

        @if (auth()->user()->isTrainer())
          <div class="nav-section">Træner</div>
          <a href="{{ route('trainer.index') }}" class="{{ request()->is('trainer*') ? 'active' : '' }}"><span class="ico"><i class="fa-solid fa-chalkboard-user"></i></span> Hold du træner</a>
        @endif

        @if (auth()->user()->isOwner())
          <div class="nav-section">Admin</div>
          <a href="{{ route('admin.courses.index') }}" class="{{ request()->is('admin/courses*') ? 'active' : '' }}"><span class="ico"><i class="fa-solid fa-clipboard-list"></i></span> Alle hold</a>
          <a href="{{ route('admin.users.index') }}" class="{{ request()->is('admin/users*') ? 'active' : '' }}"><span class="ico"><i class="fa-solid fa-users"></i></span> Brugere</a>
          <a href="{{ route('admin.settings.revenue') }}" class="{{ request()->is('admin/settings*') ? 'active' : '' }}"><span class="ico"><i class="fa-solid fa-gear"></i></span> Indstillinger</a>
        @endif
      @else
        <a href="{{ url('/hold') }}" class="{{ request()->is('/') || request()->is('hold') || request()->is('hold/*') || request()->is('calendar') ? 'active' : '' }}"><span class="ico"><i class="fa-solid fa-dumbbell"></i></span> Hold</a>
        <a href="{{ route('login') }}" class="{{ request()->is('login') ? 'active' : '' }}"><span class="ico"><i class="fa-solid fa-right-to-bracket"></i></span> Log ind</a>
        <a href="{{ route('register') }}" class="{{ request()->is('register') ? 'active' : '' }}"><span class="ico"><i class="fa-solid fa-user-plus"></i></span> Opret konto</a>
      @endauth
    </nav>
    @auth
      @if (auth()->user()->isActualOwner())
        @php $previewRole = session('preview_role'); @endphp
        <form method="POST" action="{{ route('preview.role') }}" class="preview-role-form">
          @csrf
          <label class="lbl" for="previewRoleSelect">Log in as</label>
          <select id="previewRoleSelect" name="role" onchange="this.form.submit()">
            <option value="owner" {{ !$previewRole ? 'selected' : '' }}>Ejer (mig)</option>
            <option value="trainer" {{ $previewRole === 'trainer' ? 'selected' : '' }}>Træner</option>
            <option value="assistant" {{ $previewRole === 'assistant' ? 'selected' : '' }}>Assistent</option>
            <option value="user" {{ $previewRole === 'user' ? 'selected' : '' }}>Bruger</option>
          </select>
        </form>
      @endif
      <div class="sidebar-profile-wrap">
        <a href="{{ url('/profile') }}" class="sidebar-profile {{ request()->is('profile*') ? 'active' : '' }}">
          @include('partials.avatar', ['u' => auth()->user(), 'size' => 'sm'])
          <span>{{ explode(' ', trim(auth()->user()->name))[0] }}</span>
        </a>
      </div>
      <form method="POST" action="{{ route('logout') }}" class="logout-form">
        @csrf
        <button type="submit"><span class="ico"><i class="fa-solid fa-right-from-bracket"></i></span> Log ud</button>
      </form>
    @endauth
  </aside>

  <main class="main">
    @auth
      @if (auth()->user()->isActualOwner() && session('preview_role'))
        <div class="preview-banner">
          <i class="fa-solid fa-eye"></i>
          Viser siden som <strong>{{ ['trainer' => 'Træner', 'assistant' => 'Assistent', 'user' => 'Bruger'][session('preview_role')] ?? session('preview_role') }}</strong>
          <span class="stop">
            <form method="POST" action="{{ route('preview.role') }}" style="display:inline;">
              @csrf
              <input type="hidden" name="role" value="owner">
              <button type="submit">Stop preview</button>
            </form>
          </span>
        </div>
      @endif
    @endauth
    @if (session('status'))
      <div class="alert alert-success"><i class="fa-solid fa-check-circle"></i> {{ session('status') }}</div>
    @endif
    @if ($errors->any())
      <div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i>
        <div>
          @foreach ($errors->all() as $err)<div>{{ $err }}</div>@endforeach
        </div>
      </div>
    @endif

    @yield('content')
  </main>
</div>

@auth
<script>
(function () {
  var toggle = document.getElementById('sidebarToggle');
  var sidebar = document.getElementById('sidebar');
  var backdrop = document.getElementById('sidebarBackdrop');
  function close() { sidebar.classList.remove('open'); backdrop.classList.remove('open'); }
  if (toggle) {
    toggle.addEventListener('click', function () { sidebar.classList.contains('open') ? close() : (sidebar.classList.add('open'), backdrop.classList.add('open')); });
    backdrop.addEventListener('click', close);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
    sidebar.querySelectorAll('a').forEach(function (a) { a.addEventListener('click', close); });
  }

  var titleEl = document.getElementById('topbarTitle');
  var viewHeader = document.querySelector('.view-header');
  var h1 = viewHeader ? viewHeader.querySelector('h1') : null;
  if (h1 && titleEl) {
    var txt = '';
    for (var i = 0; i < h1.childNodes.length; i++) if (h1.childNodes[i].nodeType === 3) txt += h1.childNodes[i].textContent;
    txt = txt.trim();
    if (txt) titleEl.textContent = txt;
  }

  var topbarActions = document.getElementById('topbarActions');
  var movers = [];
  if (topbarActions) {
    var fa = document.querySelector('.feed-actions');
    if (fa) movers.push({el: fa, parent: fa.parentElement});
    if (viewHeader) {
      viewHeader.querySelectorAll(':scope > a.btn, :scope > .btn, :scope > button.btn').forEach(function (b) {
        movers.push({el: b, parent: viewHeader});
      });
    }
  }
  var mql = window.matchMedia('(max-width: 767px)');
  function onMobileChange(e) {
    movers.forEach(function (m) {
      if (e.matches) topbarActions.appendChild(m.el);
      else m.parent.appendChild(m.el);
    });
    if (!viewHeader) return;
    if (e.matches) {
      if (h1) h1.style.display = 'none';
      var hasVisible = false;
      for (var c = 0; c < viewHeader.children.length; c++) if (viewHeader.children[c].offsetHeight > 0) { hasVisible = true; break; }
      viewHeader.style.display = hasVisible ? '' : 'none';
    } else {
      if (h1) h1.style.display = '';
      viewHeader.style.display = '';
    }
  }
  onMobileChange(mql);
  mql.addEventListener('change', onMobileChange);
})();
</script>
@endauth

@stack('scripts')
</body>
</html>
