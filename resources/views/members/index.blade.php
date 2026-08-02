@extends('layouts.app')
@section('content')

@push('styles')
<style>
  .members-search { position: relative; margin-bottom: 18px; max-width: 420px; }
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

  .members-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 12px; }
  .member-card {
    display: flex; flex-direction: column; overflow: hidden;
    background: #fff; border-radius: 12px; box-shadow: 0 1px 2px rgba(0,0,0,0.08);
    color: var(--text); transition: transform 0.1s, box-shadow 0.1s;
  }
  .member-card:hover { transform: translateY(-2px); box-shadow: 0 6px 14px rgba(0,0,0,0.08); }
  .member-card .info { min-width: 0; display: flex; flex-direction: column; align-items: center; gap: 8px; text-align: center; padding: 18px 14px 10px; color: inherit; text-decoration: none; }
  .member-card .name { font-weight: 700; font-size: 14px; line-height: 1.25; max-width: 100%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .member-card .info:hover .name { color: var(--accent); }

  /* The hold this person is on — each one its own link, so a card doubles as a
     way in. Pushes the role band to the bottom on short cards. */
  .member-card .holds { display: flex; flex-direction: column; gap: 1px; padding: 0 10px 12px; flex: 1; }
  .member-card .hold-link, .member-card .hold-more { font-size: 12px; text-align: center; padding: 3px 6px; border-radius: 6px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; text-decoration: none; }
  .member-card .hold-link { color: var(--accent); }
  .member-card .hold-link:hover { background: var(--accent-soft); }
  .member-card .hold-more { color: var(--muted); }
  .member-card .hold-more:hover { color: var(--text); }
  /* Nothing to list — the role band still needs pushing down. */
  .member-card .info:last-of-type { flex: 1; }
  /* Outline rather than border: a border would resize the card against its
     neighbours in the grid. */
  .member-card.you { outline: 2px solid var(--accent); outline-offset: -2px; }

  /* Role footer — one flat colour band per role, readable at a glance. */
  .role-foot { padding: 7px 10px; text-align: center; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
  .role-foot.member    { background: #dcfce7; color: #166534; }
  .role-foot.trainer   { background: #dbeafe; color: #1d4ed8; }
  .role-foot.owner     { background: #f1f5f9; color: #475569; }
  .role-foot.assistant { background: #ede9fe; color: #6d28d9; }

  .members-empty { background: #fff; border-radius: 12px; padding: 40px 20px; text-align: center; color: var(--muted); display: none; }
  .members-empty.show { display: block; }
</style>
@endpush

<div class="view-header">
  <h1><i class="fa-solid fa-circle-user" style="color:var(--text);margin-right:8px;"></i>Personer</h1>
  @include('partials.header-actions')
</div>

<div class="members-search" id="membersSearch">
  <i class="fa-solid fa-magnifying-glass icon"></i>
  <input type="search" id="membersSearchInput" placeholder="Søg efter person …" autocomplete="off">
  <button type="button" class="clear" id="membersSearchClear" aria-label="Ryd"><i class="fa-solid fa-xmark"></i></button>
</div>

<div class="members-grid" id="membersGrid">
  @foreach ($users as $u)
    @php
      // The raw column, never effectiveRole() — that reads the *viewer's*
      // preview-role session and would relabel everyone else's card.
      $roleLabel = ['owner' => 'Ejer', 'trainer' => 'Træner', 'assistant' => 'Assistent'][$u->role] ?? 'Medlem';
      $roleClass = ['owner' => 'owner', 'trainer' => 'trainer', 'assistant' => 'assistant'][$u->role] ?? 'member';

      $courseIdList = $u->activeEnrollments->pluck('course_id')
        ->concat($u->trainerCourses->pluck('id'))
        ->unique()
        ->values();

      // Resolved against the lookup, which drops anything inactive or private.
      $hold = $courseIdList->map(fn ($id) => $courses[$id] ?? null)->filter()->values();
      $shown = $hold->take(4);
      $extra = $hold->count() - $shown->count();
    @endphp
    <div class="member-card {{ $u->id === auth()->id() ? 'you' : '' }}"
         data-search="{{ strtolower($u->name) }}"
         data-courses="{{ $courseIdList->implode(',') }}">
      <a href="{{ route('members.show', $u) }}" class="info">
        @include('partials.avatar', ['u' => $u, 'size' => 'lg'])
        <div class="name">{{ $u->name }} @if ($u->id === auth()->id())<span style="color:var(--accent);font-weight:600;">(dig)</span>@endif</div>
      </a>
      @if ($shown->isNotEmpty())
        <div class="holds">
          @foreach ($shown as $c)
            <a href="{{ route('courses.show', $c) }}" class="hold-link">{{ $c->title }}</a>
          @endforeach
          @if ($extra > 0)
            <a href="{{ route('members.show', $u) }}" class="hold-more">+{{ $extra }} mere</a>
          @endif
        </div>
      @endif
      <div class="role-foot {{ $roleClass }}">{{ $roleLabel }}</div>
    </div>
  @endforeach
</div>

<div class="members-empty" id="membersEmpty">Ingen personer matcher din søgning.</div>

@push('scripts')
<script>
(function () {
  var wrap = document.getElementById('membersSearch');
  var input = document.getElementById('membersSearchInput');
  var clearBtn = document.getElementById('membersSearchClear');
  var grid = document.getElementById('membersGrid');
  var empty = document.getElementById('membersEmpty');
  if (!input || !grid) return;
  var cards = Array.prototype.slice.call(grid.querySelectorAll('.member-card'));

  function apply() {
    var q = input.value.toLowerCase().trim();
    wrap.classList.toggle('has-value', q !== '');
    var visible = 0;
    cards.forEach(function (card) {
      var hay = card.getAttribute('data-search') || '';
      var match = q === '' || hay.indexOf(q) !== -1;
      card.style.display = match ? '' : 'none';
      if (match) visible++;
    });
    empty.classList.toggle('show', visible === 0 && q !== '');
  }
  input.addEventListener('input', apply);
  clearBtn.addEventListener('click', function () { input.value = ''; apply(); input.focus(); });
})();
</script>
@endpush

@endsection
