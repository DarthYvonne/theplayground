@push('styles')
<style>
  .home-tabs { display: flex; gap: 4px; border-bottom: 1px solid var(--border); margin-bottom: 20px; overflow-x: auto; scrollbar-width: none; -ms-overflow-style: none; }
  .home-tabs::-webkit-scrollbar { display: none; }
  .home-tabs a { display: inline-flex; align-items: center; gap: 8px; padding: 10px 16px; color: var(--muted); font-weight: 600; font-size: 14px; border-bottom: 2px solid transparent; margin-bottom: -1px; white-space: nowrap; }
  .home-tabs a:hover { color: var(--text); }
  .home-tabs a.active { color: var(--accent); border-bottom-color: var(--accent); }
  .home-tabs i { font-size: 13px; }
</style>
@endpush

@php
  $mineCount = auth()->check() ? auth()->user()->activeEnrollments()->count() : 0;
  $allCount = \App\Models\Course::where('is_active', true)->count();
@endphp

<nav class="home-tabs" aria-label="Hold">
  @auth
    <a href="{{ route('catalog.mine') }}" class="{{ request()->routeIs('catalog.mine') ? 'active' : '' }}">
      <i class="fa-solid fa-user-check"></i> Dine hold ({{ $mineCount }})
    </a>
  @endauth
  <a href="{{ route('catalog') }}" class="{{ request()->routeIs('catalog') ? 'active' : '' }}">
    <i class="fa-solid fa-dumbbell"></i> Alle hold ({{ $allCount }})
  </a>
  <a href="{{ route('home.calendar') }}" class="{{ request()->routeIs('home.calendar') ? 'active' : '' }}">
    <i class="fa-regular fa-calendar"></i> Kalender
  </a>
</nav>
