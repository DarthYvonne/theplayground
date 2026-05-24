@push('styles')
<style>
  .dash-tabs { display: flex; gap: 4px; border-bottom: 1px solid var(--border); margin-bottom: 20px; overflow-x: auto; scrollbar-width: none; -ms-overflow-style: none; }
  .dash-tabs::-webkit-scrollbar { display: none; }
  .dash-tabs a { display: inline-flex; align-items: center; gap: 8px; padding: 10px 16px; color: var(--muted); font-weight: 600; font-size: 14px; border-bottom: 2px solid transparent; margin-bottom: -1px; white-space: nowrap; }
  .dash-tabs a:hover { color: var(--text); }
  .dash-tabs a.active { color: var(--accent); border-bottom-color: var(--accent); }
  .dash-tabs i { font-size: 13px; }
</style>
@endpush

<nav class="dash-tabs" aria-label="Start">
  <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">
    <i class="fa-regular fa-newspaper"></i> Feed
  </a>
  <a href="{{ route('dashboard.hold') }}" class="{{ request()->routeIs('dashboard.hold') ? 'active' : '' }}">
    <i class="fa-solid fa-dumbbell"></i> Dine hold
  </a>
</nav>
