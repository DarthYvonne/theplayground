@push('styles')
<style>
  .courses-tabs { display: flex; gap: 4px; border-bottom: 1px solid var(--border); margin-bottom: 20px; overflow-x: auto; scrollbar-width: none; -ms-overflow-style: none; }
  .courses-tabs::-webkit-scrollbar { display: none; }
  .courses-tabs a { display: inline-flex; align-items: center; gap: 8px; padding: 10px 16px; color: var(--muted); font-weight: 600; font-size: 14px; border-bottom: 2px solid transparent; margin-bottom: -1px; white-space: nowrap; }
  .courses-tabs a:hover { color: var(--text); }
  .courses-tabs a.active { color: var(--accent); border-bottom-color: var(--accent); }
  .courses-tabs i { font-size: 13px; }
</style>
@endpush

<nav class="courses-tabs" aria-label="Hold">
  <a href="{{ route('admin.courses.index') }}" class="{{ request()->routeIs('admin.courses.index') ? 'active' : '' }}">
    <i class="fa-solid fa-dumbbell"></i> Hold
  </a>
  <a href="{{ route('admin.courses.calendar') }}" class="{{ request()->routeIs('admin.courses.calendar') ? 'active' : '' }}">
    <i class="fa-regular fa-calendar"></i> Kalender
  </a>
</nav>
