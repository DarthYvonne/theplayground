@push('styles')
<style>
  .settings-tabs { display: flex; gap: 4px; border-bottom: 1px solid var(--border); margin-bottom: 20px; overflow-x: auto; scrollbar-width: none; -ms-overflow-style: none; }
  .settings-tabs::-webkit-scrollbar { display: none; }
  .settings-tabs a { display: inline-flex; align-items: center; gap: 8px; padding: 10px 16px; color: var(--muted); font-weight: 600; font-size: 14px; border-bottom: 2px solid transparent; margin-bottom: -1px; white-space: nowrap; }
  .settings-tabs a:hover { color: var(--text); }
  .settings-tabs a.active { color: var(--accent); border-bottom-color: var(--accent); }
  .settings-tabs i { font-size: 13px; }
</style>
@endpush

<nav class="settings-tabs" aria-label="Indstillinger">
  <a href="{{ route('admin.settings.revenue') }}" class="{{ request()->routeIs('admin.settings.revenue') ? 'active' : '' }}">
    <i class="fa-solid fa-chart-line"></i> Omsætning
  </a>
  <a href="{{ route('admin.users.index') }}" class="{{ request()->routeIs('admin.users.index') ? 'active' : '' }}">
    <i class="fa-solid fa-users"></i> Brugere
  </a>
</nav>
