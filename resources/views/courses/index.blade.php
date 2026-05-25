@extends('layouts.app')
@section('content')

@push('styles')
<style>
  .hero { background: linear-gradient(135deg, var(--accent) 0%, #6cabff 100%); color: #fff; padding: 24px 22px; border-radius: 14px; margin-bottom: 18px; }
  .hero h2 { font-size: 22px; font-weight: 800; margin-bottom: 6px; line-height: 1.2; }
  .hero p { font-size: 14px; opacity: 0.95; margin-bottom: 12px; }
  .hero .btn { background: #fff; color: var(--accent); }
  .hero .btn:hover { background: #f0f4fc; }
  .hero .btn.ghost { background: transparent; color: #fff; border: 1px solid rgba(255,255,255,0.6); margin-left: 6px; }
  .empty-feed { background: #fff; border-radius: 12px; padding: 60px 20px; text-align: center; color: var(--muted); }

  /* User-facing course tile overrides */
  a.course-tile { color: inherit; text-decoration: none; transition: transform 0.1s, box-shadow 0.1s; }
  a.course-tile:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(0,0,0,0.08); }
  .course-tile .img-wrap { position: relative; }
  .course-tile-title { font-size: 18px; line-height: 1.25; }
  .course-tile-sched { color: var(--muted); font-size: 13px; margin-top: 8px; display: flex; align-items: center; gap: 6px; }
  .course-tile-price { font-weight: 700; font-size: 15px; margin-top: 10px; color: var(--text); }
  .course-tile-status { color: var(--muted); font-size: 13px; margin-top: 4px; }
  .course-tile-enrolled-badge { position: absolute; top: 10px; right: 10px; background: #16a34a; color: #fff; font-size: 12px; font-weight: 700; padding: 4px 10px; border-radius: 999px; box-shadow: 0 2px 6px rgba(0,0,0,0.18); display: inline-flex; align-items: center; gap: 5px; }

  /* Live search */
  .hold-search { position: relative; margin-bottom: 18px; max-width: 420px; }
  .hold-search input[type=search] {
    -webkit-appearance: none; appearance: none;
    width: 100%; font-family: inherit; font-size: 14px;
    padding: 10px 38px 10px 38px;
    border: 1px solid var(--border); border-radius: 8px;
    background: #fff; color: var(--text); line-height: 1.2;
  }
  .hold-search input[type=search]:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px rgba(24,119,242,0.15); }
  .hold-search input[type=search]::-webkit-search-decoration,
  .hold-search input[type=search]::-webkit-search-cancel-button,
  .hold-search input[type=search]::-webkit-search-results-button,
  .hold-search input[type=search]::-webkit-search-results-decoration { -webkit-appearance: none; display: none; }
  .hold-search .icon { position: absolute; left: 13px; top: 50%; transform: translateY(-50%); color: var(--muted); pointer-events: none; font-size: 14px; }
  .hold-search .clear { position: absolute; right: 6px; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--muted); cursor: pointer; padding: 6px 8px; border-radius: 6px; display: none; font-size: 13px; line-height: 1; }
  .hold-search .clear:hover { background: var(--hover); color: var(--text); }
  .hold-search.has-value .clear { display: block; }
  .hold-no-results { display: none; padding: 32px 20px; text-align: center; color: var(--muted); background: #fff; border-radius: 12px; box-shadow: 0 1px 2px rgba(0,0,0,0.08); }
  .hold-no-results.show { display: block; }
</style>
@endpush

@auth
<div class="view-header">
  <h1><i class="fa-solid fa-dumbbell" style="color:var(--text);margin-right:8px;"></i>Hold</h1>
  @include('partials.header-actions')
</div>

@include('courses._subnav')

@if (!$courses->isEmpty())
  <div class="hold-search" id="holdSearch">
    <i class="fa-solid fa-magnifying-glass icon"></i>
    <input type="search" id="holdSearchInput" placeholder="Søg efter hold, træner eller dag …" autocomplete="off">
    <button type="button" class="clear" id="holdSearchClear" aria-label="Ryd"><i class="fa-solid fa-xmark"></i></button>
  </div>
@endif
@endauth

@guest
<div class="hero">
  <h2>Find dit næste hold</h2>
  <p>Se hvilke hold der kører på The Playground. Opret en konto for at tilmelde dig og chatte med trænere og andre medlemmer.</p>
  <a href="{{ route('register') }}" class="btn">Kom i gang</a>
  <a href="{{ route('login') }}" class="btn ghost">Log ind</a>
</div>
@endguest

@if ($courses->isEmpty())
  <div class="empty-feed">
    <h3 style="color:var(--text);margin-bottom:6px;">Ingen hold endnu</h3>
    <p>Kom tilbage om lidt.</p>
  </div>
@else
  <div class="course-grid" id="holdGrid">
    @foreach ($courses as $course)
      @php
        $full = $course->active_enrollments_count >= $course->max_participants;
        $enrolled = auth()->check() && auth()->user()->enrolledIn($course);
      @endphp
      <a href="{{ route('courses.show', $course) }}" class="card course-tile" data-search="{{ strtolower($course->title . ' ' . $course->trainer->name . ' ' . ($course->scheduleLabel() ?? '')) }}" aria-label="{{ $course->title }}">
        <div class="img-wrap">
          @if ($course->image_path)
            <img src="{{ $course->imageUrl() }}" alt="" class="course-tile-img">
          @else
            <div class="course-tile-img course-tile-img-ph"><i class="fa-solid fa-dumbbell"></i></div>
          @endif
          @if ($enrolled)
            <span class="course-tile-enrolled-badge"><i class="fa-solid fa-circle-check"></i> Tilmeldt</span>
          @endif
        </div>
        <div class="card-pad">
          <div class="course-tile-title">{{ $course->title }}</div>
          @if ($course->scheduleLabel())
            <div class="course-tile-sched"><i class="fa-regular fa-clock"></i>{{ $course->scheduleLabel() }}</div>
          @endif
          <div class="course-tile-price">{{ $course->price() }}</div>
          <div class="course-tile-status">
            {{ $course->active_enrollments_count }}/{{ $course->max_participants }} tilmeldt
            @if ($full) · <span style="color:#b91c1c;font-weight:600;">Fuldt</span>@endif
          </div>
        </div>
      </a>
    @endforeach
  </div>
  <div class="hold-no-results" id="holdNoResults">Ingen hold matcher din søgning.</div>
@endif

@push('scripts')
<script>
(function () {
  var wrap = document.getElementById('holdSearch');
  var input = document.getElementById('holdSearchInput');
  var clearBtn = document.getElementById('holdSearchClear');
  var grid = document.getElementById('holdGrid');
  var noResults = document.getElementById('holdNoResults');
  if (!input || !grid) return;
  var tiles = Array.prototype.slice.call(grid.querySelectorAll('.course-tile'));

  function apply() {
    var q = input.value.toLowerCase().trim();
    wrap.classList.toggle('has-value', q !== '');
    var visible = 0;
    tiles.forEach(function (tile) {
      var hay = tile.getAttribute('data-search') || '';
      var match = q === '' || hay.indexOf(q) !== -1;
      tile.style.display = match ? '' : 'none';
      if (match) visible++;
    });
    noResults.classList.toggle('show', visible === 0 && q !== '');
  }

  input.addEventListener('input', apply);
  clearBtn.addEventListener('click', function () { input.value = ''; apply(); input.focus(); });
})();
</script>
@endpush

@endsection
