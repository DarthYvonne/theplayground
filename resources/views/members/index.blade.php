@extends('layouts.app')
@section('content')

@push('styles')
<style>
  .members-filter { display: flex; gap: 10px; margin-bottom: 18px; flex-wrap: wrap; align-items: stretch; }
  .members-search { position: relative; flex: 1; min-width: 220px; max-width: 420px; }
  .members-search input[type=search] {
    -webkit-appearance: none; appearance: none;
    width: 100%; font-family: inherit; font-size: 14px;
    padding: 10px 38px 10px 38px;
    border: 1px solid var(--border); border-radius: 8px;
    background: #fff; color: var(--text); line-height: 1.2;
  }
  .members-search input[type=search]:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px rgba(24,119,242,0.15); }
  .members-search input[type=search]::-webkit-search-decoration,
  .members-search input[type=search]::-webkit-search-cancel-button { -webkit-appearance: none; display: none; }
  .members-search .icon { position: absolute; left: 13px; top: 50%; transform: translateY(-50%); color: var(--muted); pointer-events: none; font-size: 14px; }
  .members-search .clear { position: absolute; right: 6px; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--muted); cursor: pointer; padding: 6px 8px; border-radius: 6px; display: none; font-size: 13px; line-height: 1; }
  .members-search .clear:hover { background: var(--hover); color: var(--text); }
  .members-search.has-value .clear { display: block; }

  .members-hold-select {
    -webkit-appearance: none; appearance: none;
    font-family: inherit; font-size: 14px; line-height: 1.2;
    padding: 10px 34px 10px 14px;
    border: 1px solid var(--border); border-radius: 8px;
    background: #fff; color: var(--text);
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 8'%3E%3Cpath fill='%2365676b' d='M6 8 0 0h12z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 12px center;
    background-size: 10px 7px;
    cursor: pointer;
    min-width: 180px;
  }
  .members-hold-select:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px rgba(24,119,242,0.15); }
  .members-hold-select.active { border-color: var(--accent); color: var(--accent); font-weight: 600; }

  .members-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 12px; }
  .member-card {
    display: flex; gap: 12px; align-items: center;
    background: #fff; border-radius: 12px; box-shadow: 0 1px 2px rgba(0,0,0,0.08);
    padding: 12px 14px; color: var(--text);
    transition: transform 0.1s, box-shadow 0.1s;
  }
  .member-card:hover { transform: translateY(-2px); box-shadow: 0 6px 14px rgba(0,0,0,0.08); }
  .member-card .info { min-width: 0; flex: 1; }
  .member-card .name { font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .member-card .meta { color: var(--muted); font-size: 12px; margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .member-card.you { border: 1px solid var(--accent); }

  .members-empty { background: #fff; border-radius: 12px; padding: 40px 20px; text-align: center; color: var(--muted); display: none; }
  .members-empty.show { display: block; }
</style>
@endpush

<div class="view-header">
  <h1><i class="fa-solid fa-users" style="color:var(--text);margin-right:8px;"></i>Medlemmer</h1>
  @include('partials.header-actions')
</div>

<div class="members-filter">
  <div class="members-search" id="membersSearch">
    <i class="fa-solid fa-magnifying-glass icon"></i>
    <input type="search" id="membersSearchInput" placeholder="Søg efter medlem …" autocomplete="off">
    <button type="button" class="clear" id="membersSearchClear" aria-label="Ryd"><i class="fa-solid fa-xmark"></i></button>
  </div>
  <select class="members-hold-select" id="membersHoldSelect" aria-label="Filtrér på hold">
    <option value="">Alle hold</option>
    @foreach ($courses as $c)
      <option value="{{ $c->id }}">{{ $c->title }}</option>
    @endforeach
  </select>
</div>

<div class="members-grid" id="membersGrid">
  @foreach ($users as $u)
    @php
      $roleLabel = ['trainer' => 'Træner', 'assistant' => 'Assistent'][$u->role] ?? null;
      if (!$roleLabel) {
        if ($u->active_enrollments_count > 0) {
          $roleLabel = $u->active_enrollments_count . ' hold';
        } else {
          $roleLabel = 'Medlem';
        }
      } elseif ($u->role === 'trainer' && $u->trainer_courses_count > 0) {
        $roleLabel = 'Træner · ' . $u->trainer_courses_count . ' hold';
      }
      $courseIds = $u->activeEnrollments->pluck('course_id')
        ->concat($u->trainerCourses->pluck('id'))
        ->unique()
        ->values()
        ->implode(',');
    @endphp
    <a href="{{ route('members.show', $u) }}"
       class="member-card {{ $u->id === auth()->id() ? 'you' : '' }}"
       data-search="{{ strtolower($u->name) }}"
       data-courses="{{ $courseIds }}">
      @include('partials.avatar', ['u' => $u])
      <div class="info">
        <div class="name">{{ $u->name }} @if ($u->id === auth()->id())<span style="color:var(--accent);font-size:12px;font-weight:600;">(dig)</span>@endif</div>
        <div class="meta">{{ $roleLabel }}</div>
      </div>
    </a>
  @endforeach
</div>

<div class="members-empty" id="membersEmpty">Ingen medlemmer matcher din søgning.</div>

@push('scripts')
<script>
(function () {
  var wrap = document.getElementById('membersSearch');
  var input = document.getElementById('membersSearchInput');
  var clearBtn = document.getElementById('membersSearchClear');
  var holdSelect = document.getElementById('membersHoldSelect');
  var grid = document.getElementById('membersGrid');
  var empty = document.getElementById('membersEmpty');
  if (!input || !grid) return;
  var cards = Array.prototype.slice.call(grid.querySelectorAll('.member-card'));

  function apply() {
    var q = input.value.toLowerCase().trim();
    var hold = holdSelect.value;
    wrap.classList.toggle('has-value', q !== '');
    holdSelect.classList.toggle('active', hold !== '');
    var visible = 0;
    cards.forEach(function (card) {
      var hay = card.getAttribute('data-search') || '';
      var courses = (card.getAttribute('data-courses') || '').split(',').filter(Boolean);
      var matchText = q === '' || hay.indexOf(q) !== -1;
      var matchHold = hold === '' || courses.indexOf(hold) !== -1;
      var match = matchText && matchHold;
      card.style.display = match ? '' : 'none';
      if (match) visible++;
    });
    empty.classList.toggle('show', visible === 0 && (q !== '' || hold !== ''));
  }
  input.addEventListener('input', apply);
  holdSelect.addEventListener('change', apply);
  clearBtn.addEventListener('click', function () { input.value = ''; apply(); input.focus(); });
})();
</script>
@endpush

@endsection
