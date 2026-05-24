@push('styles')
<style>
  .trainer-tabs { display: flex; gap: 4px; border-bottom: 1px solid var(--border); margin-bottom: 20px; overflow-x: auto; scrollbar-width: none; -ms-overflow-style: none; }
  .trainer-tabs::-webkit-scrollbar { display: none; }
  .trainer-tabs a { display: inline-flex; align-items: center; gap: 8px; padding: 10px 16px; color: var(--muted); font-weight: 600; font-size: 14px; border-bottom: 2px solid transparent; margin-bottom: -1px; white-space: nowrap; }
  .trainer-tabs a:hover { color: var(--text); }
  .trainer-tabs a.active { color: var(--accent); border-bottom-color: var(--accent); }
  .trainer-tabs i { font-size: 13px; }
</style>
@endpush

<nav class="trainer-tabs" aria-label="Træner">
  <a href="{{ route('trainer.index') }}" class="{{ request()->routeIs('trainer.index') ? 'active' : '' }}">
    <i class="fa-solid fa-dumbbell"></i> Hold
  </a>
  <a href="{{ route('trainer.calendar') }}" class="{{ request()->routeIs('trainer.calendar') ? 'active' : '' }}">
    <i class="fa-regular fa-calendar"></i> Kalender
  </a>
</nav>
