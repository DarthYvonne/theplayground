@push('styles')
<style>
  .profile-tabs { display: flex; gap: 4px; border-bottom: 1px solid var(--border); margin-bottom: 20px; overflow-x: auto; scrollbar-width: none; -ms-overflow-style: none; }
  .profile-tabs::-webkit-scrollbar { display: none; }
  .profile-tabs a { display: inline-flex; align-items: center; gap: 8px; padding: 10px 16px; color: var(--muted); font-weight: 600; font-size: 14px; border-bottom: 2px solid transparent; margin-bottom: -1px; white-space: nowrap; }
  .profile-tabs a:hover { color: var(--text); }
  .profile-tabs a.active { color: var(--accent); border-bottom-color: var(--accent); }
  .profile-tabs i { font-size: 13px; }
</style>
@endpush

<nav class="profile-tabs" aria-label="Profil">
  <a href="{{ route('profile.edit') }}" class="{{ request()->routeIs('profile.edit') ? 'active' : '' }}">
    <i class="fa-regular fa-user"></i> Profil
  </a>
  <a href="{{ route('profile.billing') }}" class="{{ request()->routeIs('profile.billing') ? 'active' : '' }}">
    <i class="fa-regular fa-credit-card"></i> Betaling
  </a>
</nav>
