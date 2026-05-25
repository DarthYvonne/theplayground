@extends('layouts.app')
@section('content')

@push('styles')
<style>
  .float-shell { max-width: 1100px; }
  .float-head { display: flex; align-items: center; gap: 12px; margin-bottom: 14px; flex-wrap: wrap; }
  .float-head .price { background: var(--accent-soft); color: var(--accent); padding: 6px 12px; border-radius: 999px; font-weight: 700; font-size: 13px; }
  .float-head .nav { margin-left: auto; display: inline-flex; gap: 6px; align-items: center; color: var(--muted); font-size: 14px; }
  .float-head .nav a { color: var(--muted); padding: 6px 10px; border-radius: 6px; }
  .float-head .nav a:hover { background: var(--hover); color: var(--text); }
  .float-head .nav .label { padding: 0 8px; font-weight: 600; color: var(--text); }

  .float-empty { background: #fff; border-radius: 12px; padding: 40px 20px; text-align: center; color: var(--muted); box-shadow: 0 1px 2px rgba(0,0,0,0.08); }
  .float-empty h3 { color: var(--text); margin-bottom: 6px; }

  .float-grid { background: #fff; border-radius: 12px; box-shadow: 0 1px 2px rgba(0,0,0,0.08); overflow: hidden; }
  .float-grid table { width: 100%; border-collapse: collapse; }
  .float-grid th, .float-grid td { border-bottom: 1px solid #f0f2f5; padding: 0; font-size: 13px; vertical-align: top; }
  .float-grid thead th { background: #fafbfc; padding: 10px 8px; text-align: left; font-weight: 700; color: var(--text); font-size: 13px; }
  .float-grid thead th.today { color: var(--accent); }
  .float-grid thead th.closed { color: var(--muted); }
  .float-grid tbody th { padding: 8px 10px; color: var(--muted); font-weight: 600; white-space: nowrap; background: #fafbfc; min-width: 64px; }
  .float-grid td.cell { padding: 6px; }
  .float-grid .slot { display: flex; gap: 6px; align-items: center; border: 1px solid var(--border); background: #fff; border-radius: 8px; padding: 8px 10px; cursor: pointer; transition: background 0.1s, border-color 0.1s; font: inherit; width: 100%; text-align: left; color: var(--muted); min-height: 36px; }
  .float-grid .slot:hover { background: var(--accent-soft); border-color: var(--accent); }
  .float-grid .slot.full { background: #f5f6f8; border-color: #e7eaee; color: var(--muted); cursor: not-allowed; justify-content: center; font-size: 11px; }
  .float-grid .slot.full:hover { background: #f5f6f8; border-color: #e7eaee; }
  .float-grid .slot.mine { background: #ecfdf5; border-color: #34d399; color: #065f46; flex-direction: column; align-items: flex-start; gap: 0; }
  .float-grid .slot.mine .lbl { font-weight: 700; font-size: 12px; }
  .float-grid .slot.mine .sub { font-size: 11px; }
  .float-grid .slot.has-mine { border-color: #34d399; background: #f5fdf9; }
  .float-grid .slot .mine-dot { color: #16a34a; font-size: 10px; }
  .float-grid .slot.past { opacity: 0.45; cursor: not-allowed; }
  .float-grid .slot .avail-chip { display: inline-flex; align-items: center; gap: 3px; font-size: 11px; color: #166534; font-weight: 600; }
  .float-grid .slot .avail-chip i { font-size: 10px; opacity: 0.75; }
  .float-grid td.closed { background: #fafbfc; color: var(--muted); text-align: center; padding: 18px 8px; font-size: 12px; }

  /* Booking modal */
  .book-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,0.45); z-index: 9998; display: none; align-items: center; justify-content: center; padding: 20px; }
  .book-backdrop.open { display: flex; }
  .book-modal { background: #fff; border-radius: 12px; width: 100%; max-width: 460px; box-shadow: 0 10px 32px rgba(0,0,0,0.22); overflow: hidden; }
  .book-head { padding: 16px 20px; border-bottom: 1px solid #f0f2f5; display: flex; align-items: center; gap: 10px; }
  .book-head .title { font-weight: 700; flex: 1; font-size: 16px; }
  .book-head .close { background: none; border: none; cursor: pointer; padding: 6px 10px; border-radius: 6px; color: var(--muted); font-size: 16px; }
  .book-body { padding: 16px 20px; }
  .book-body .when { color: var(--muted); font-size: 13px; margin-bottom: 12px; }
  .book-body .device-list { display: flex; flex-direction: column; gap: 8px; }
  .book-body .device-pick { display: flex; align-items: center; gap: 10px; padding: 12px 14px; border: 1px solid var(--border); border-radius: 10px; cursor: pointer; background: #fff; }
  .book-body .device-pick:hover { border-color: var(--accent); }
  .book-body .device-pick.taken { opacity: 0.4; cursor: not-allowed; background: #fafbfc; }
  .book-body .device-pick.mine { background: #ecfdf5; border-color: #34d399; cursor: default; }
  .book-body .device-pick.mine .meta { color: #065f46; font-weight: 700; }
  .book-body .device-pick .nm { font-weight: 600; flex: 1; }
  .book-body .device-pick .meta { color: var(--muted); font-size: 12px; }
  .book-foot { padding: 12px 20px; border-top: 1px solid #f0f2f5; display: flex; gap: 8px; align-items: center; }
  .book-foot .hint { color: var(--muted); font-size: 12px; flex: 1; }

  /* My upcoming */
  .my-up { margin-bottom: 18px; background: #fff; border-radius: 12px; padding: 14px 16px; box-shadow: 0 1px 2px rgba(0,0,0,0.08); }
  .my-up h2 { font-size: 14px; font-weight: 700; margin-bottom: 10px; }
  .my-up .row { display: flex; align-items: center; gap: 10px; padding: 8px 0; border-bottom: 1px solid #f0f2f5; }
  .my-up .row:last-child { border-bottom: none; }
  .my-up .row .meta { flex: 1; }
  .my-up .row .when { font-weight: 600; }
  .my-up .row .sub { color: var(--muted); font-size: 12px; }
  .my-up .row .actions { display: inline-flex; gap: 6px; }

  /* Mobile single-day view */
  .float-mobile { display: none; }
  .float-mobile .day-tabs { display: flex; gap: 6px; overflow-x: auto; padding: 2px 2px 10px; margin-bottom: 6px; scrollbar-width: none; }
  .float-mobile .day-tabs::-webkit-scrollbar { display: none; }
  .float-mobile .day-tab { flex: 0 0 auto; display: inline-flex; flex-direction: column; align-items: center; gap: 1px; min-width: 52px; padding: 8px 6px; border: 1px solid var(--border); background: #fff; border-radius: 10px; cursor: pointer; font: inherit; color: inherit; line-height: 1.15; }
  .float-mobile .day-tab .dl { font-size: 11px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: 0.4px; }
  .float-mobile .day-tab .dn { font-size: 15px; font-weight: 700; }
  .float-mobile .day-tab.closed .dl, .float-mobile .day-tab.closed .dn { color: var(--muted); }
  .float-mobile .day-tab.today { color: var(--accent); border-color: var(--accent); }
  .float-mobile .day-tab.today .dl { color: var(--accent); }
  .float-mobile .day-tab.active { background: var(--accent); border-color: var(--accent); color: #fff; }
  .float-mobile .day-tab.active .dl, .float-mobile .day-tab.active .dn { color: #fff; }
  .float-mobile .day-panel { display: none; background: #fff; border-radius: 12px; box-shadow: 0 1px 2px rgba(0,0,0,0.08); overflow: hidden; }
  .float-mobile .day-panel.active { display: block; }
  .float-mobile .day-panel .day-empty { padding: 30px 18px; text-align: center; color: var(--muted); font-size: 13px; }
  .float-mobile .slot-row { display: flex; align-items: center; gap: 12px; padding: 12px 14px; border-bottom: 1px solid #f0f2f5; cursor: pointer; background: #fff; }
  .float-mobile .slot-row:last-child { border-bottom: none; }
  .float-mobile .slot-row:hover { background: var(--accent-soft); }
  .float-mobile .slot-row .time { font-weight: 700; font-size: 14px; min-width: 96px; color: var(--text); }
  .float-mobile .slot-row .state { margin-left: auto; display: inline-flex; gap: 8px; align-items: center; flex-wrap: wrap; justify-content: flex-end; }
  .float-mobile .slot-row.full { background: #fafbfc; color: var(--muted); cursor: not-allowed; }
  .float-mobile .slot-row.full:hover { background: #fafbfc; }
  .float-mobile .slot-row.mine { background: #ecfdf5; cursor: not-allowed; }
  .float-mobile .slot-row.mine:hover { background: #ecfdf5; }
  .float-mobile .slot-row.mine .state { color: #065f46; font-weight: 700; font-size: 13px; }
  .float-mobile .slot-row.has-mine .time::before { content: '\f058'; font-family: 'Font Awesome 6 Free'; font-weight: 900; color: #16a34a; margin-right: 6px; font-size: 12px; }
  .float-mobile .slot-row.past { opacity: 0.5; cursor: not-allowed; }
  .float-mobile .slot-row.past:hover { background: #fff; }
  .float-mobile .slot-row .avail-chip { display: inline-flex; align-items: center; gap: 4px; font-size: 12px; color: #166534; font-weight: 600; }
  .float-mobile .slot-row .avail-chip i { font-size: 11px; opacity: 0.75; }
  .float-mobile .slot-row .state .full-tag { font-size: 12px; color: var(--muted); }

  @media (max-width: 767px) {
    .float-grid { display: none; }
    .float-mobile { display: block; }
    .float-head .price { font-size: 12px; padding: 5px 10px; }
    .float-head .nav .label { padding: 0 4px; font-size: 12px; }
  }
</style>
@endpush

<div class="view-header">
  <h1><i class="fa-solid fa-water" style="color:var(--accent);margin-right:8px;"></i>Floating</h1>
  @include('partials.header-actions')
</div>

@if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if ($errors->any())<div class="alert alert-error">{{ $errors->first() }}</div>@endif

<div class="float-shell">
  @auth
    @if ($myUpcoming->isNotEmpty())
      <div class="my-up">
        <h2><i class="fa-regular fa-calendar-check" style="margin-right:6px;color:var(--accent);"></i>Mine kommende bookinger</h2>
        @foreach ($myUpcoming as $b)
          <div class="row">
            <div class="meta">
              <div class="when">{{ $b->slot_start->format('d.m.Y · H:i') }}–{{ $b->slot_end->format('H:i') }}</div>
              <div class="sub">{{ $b->device->name ?? 'Tank' }}</div>
            </div>
            <div class="actions">
              @if ($isOwner || $b->isCancellable($settings->cancel_cutoff_hours))
                <form method="POST" action="{{ route('floating.cancel', $b) }}" onsubmit="return confirm('Aflys denne booking?');">
                  @csrf
                  <button class="btn btn-ghost btn-sm" type="submit"><i class="fa-solid fa-xmark"></i> Aflys</button>
                </form>
              @else
                <span style="color:var(--muted);font-size:12px;">Inden for afbestillingsfrist</span>
              @endif
            </div>
          </div>
        @endforeach
      </div>
    @endif
  @endauth

  <div class="float-head">
    <span class="price">Enkelt {{ $settings->priceLabelFor('single') }} · Dobbelt {{ $settings->priceLabelFor('double') }} · {{ $settings->slot_duration_minutes }} min</span>
    @if ($devices->isEmpty())
      <span style="color:var(--muted);font-size:13px;">Ingen tanke endnu @if ($isOwner) — <a href="{{ route('admin.settings.floating') }}">tilføj i Indstillinger</a>@endif.</span>
    @endif
    @php
      $prevMonday = $monday->copy()->subDays(7);
      $nextMonday = $monday->copy()->addDays(7);
    @endphp
    <div class="nav">
      <a href="{{ route('floating.index', ['week' => \App\Support\CalendarWeek::weekParam($prevMonday)]) }}" aria-label="Forrige uge"><i class="fa-solid fa-chevron-left"></i></a>
      <span class="label">Uge {{ $monday->isoWeek }} · {{ $monday->format('d.m') }}–{{ $monday->copy()->addDays(6)->format('d.m.Y') }}</span>
      <a href="{{ route('floating.index', ['week' => \App\Support\CalendarWeek::weekParam($nextMonday)]) }}" aria-label="Næste uge"><i class="fa-solid fa-chevron-right"></i></a>
    </div>
  </div>

  @if ($devices->isEmpty() || empty($slots))
    <div class="float-empty">
      <h3>Floating er ikke konfigureret endnu</h3>
      <p>@if ($isOwner)Gå til <a href="{{ route('admin.settings.floating') }}">Indstillinger → Floating</a> for at oprette tanke og sætte åbningstider.@else Kontakt The Playground for at høre mere.@endif</p>
    </div>
  @else
    <div class="float-grid">
      <table>
        <thead>
          <tr>
            <th></th>
            @foreach ($days as $day)
              @php $d = \Carbon\Carbon::parse($day['date']); $isToday = $d->isToday(); @endphp
              <th class="{{ $isToday ? 'today' : '' }} {{ !$day['is_open'] ? 'closed' : '' }}">
                {{ $day['label'] }}<br><span style="font-weight:400;font-size:11px;color:var(--muted);">{{ $day['short'] }}</span>
              </th>
            @endforeach
          </tr>
        </thead>
        <tbody>
          @foreach ($slots as $hhmm)
            @php
              $slotEnd = \Carbon\Carbon::createFromFormat('H:i', $hhmm)->addMinutes($settings->slot_duration_minutes)->format('H:i');
            @endphp
            <tr>
              <th>{{ $hhmm }}–{{ $slotEnd }}</th>
              @foreach ($days as $day)
                @php
                  $cell = $grid[$day['date']][$hhmm] ?? null;
                  $slotStart = \Carbon\Carbon::parse($day['date'] . ' ' . $hhmm);
                  $isPast = $slotStart->isPast();
                @endphp
                @php $hasMine = (bool) ($cell['mine'] ?? false); @endphp
                @if (!$day['is_open'])
                  <td class="closed">@if ($loop->first && $hhmm === ($slots[0] ?? null))Lukket @endif</td>
                @elseif ($hasMine && $cell['free_count'] <= 0)
                  <td class="cell">
                    <button type="button" class="slot mine" disabled>
                      <span class="lbl">Din booking</span>
                      <span class="sub">{{ $cell['mine']->device->name ?? 'Tank' }}</span>
                    </button>
                  </td>
                @elseif ($cell['free_count'] <= 0)
                  <td class="cell">
                    <button type="button" class="slot full" disabled>Fuldt booket</button>
                  </td>
                @else
                  <td class="cell">
                    <button type="button"
                      class="slot {{ $hasMine ? 'has-mine' : '' }} {{ $isPast ? 'past' : '' }}"
                      {{ $isPast ? 'disabled' : '' }}
                      data-slot="{{ $day['date'] }} {{ $hhmm }}"
                      data-taken="{{ implode(',', $cell['taken_device_ids']) }}"
                      data-mine="{{ implode(',', $cell['mine_device_ids'] ?? []) }}"
                      title="{{ $hasMine ? 'Du har booket — book endnu en tank til en ven' : '' }}"
                    >
                      @if ($hasMine)
                        <span class="mine-dot" title="Du har en booking på dette slot"><i class="fa-solid fa-circle-check"></i></span>
                      @endif
                      @if (($cell['free_by_type']['single'] ?? 0) > 0)
                        <span class="avail-chip" title="Enkelt-tanke ledige"><i class="fa-solid fa-user"></i>{{ $cell['free_by_type']['single'] }}</span>
                      @endif
                      @if (($cell['free_by_type']['double'] ?? 0) > 0)
                        <span class="avail-chip" title="Dobbelt-tanke ledige"><i class="fa-solid fa-user-group"></i>{{ $cell['free_by_type']['double'] }}</span>
                      @endif
                    </button>
                  </td>
                @endif
              @endforeach
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    @php
      $todayStr = \Carbon\Carbon::today()->toDateString();
      $todayKey = collect($days)->firstWhere('date', $todayStr)['date'] ?? null;
      $defaultDayKey = $todayKey ?? ($days[0]['date'] ?? null);
    @endphp
    <div class="float-mobile">
      <div class="day-tabs" role="tablist">
        @foreach ($days as $day)
          @php $isToday = $day['date'] === $todayStr; @endphp
          <button type="button" class="day-tab {{ $isToday ? 'today' : '' }} {{ !$day['is_open'] ? 'closed' : '' }} {{ $day['date'] === $defaultDayKey ? 'active' : '' }}" data-day="{{ $day['date'] }}" role="tab">
            <span class="dl">{{ $day['label'] }}</span>
            <span class="dn">{{ $day['short'] }}</span>
          </button>
        @endforeach
      </div>

      @foreach ($days as $day)
        <div class="day-panel {{ $day['date'] === $defaultDayKey ? 'active' : '' }}" data-day="{{ $day['date'] }}" role="tabpanel">
          @if (!$day['is_open'])
            <div class="day-empty">Lukket</div>
          @else
            @foreach ($slots as $hhmm)
              @php
                $slotEnd = \Carbon\Carbon::createFromFormat('H:i', $hhmm)->addMinutes($settings->slot_duration_minutes)->format('H:i');
                $cell = $grid[$day['date']][$hhmm] ?? null;
                $slotStart = \Carbon\Carbon::parse($day['date'] . ' ' . $hhmm);
                $isPast = $slotStart->isPast();
                $hasMine = (bool) ($cell['mine'] ?? false);
              @endphp
              @if ($hasMine && $cell['free_count'] <= 0)
                <div class="slot-row mine">
                  <div class="time">{{ $hhmm }}–{{ $slotEnd }}</div>
                  <div class="state">Din booking · {{ $cell['mine']->device->name ?? 'Tank' }}</div>
                </div>
              @elseif ($cell['free_count'] <= 0)
                <div class="slot-row full">
                  <div class="time">{{ $hhmm }}–{{ $slotEnd }}</div>
                  <div class="state"><span class="full-tag">Fuldt booket</span></div>
                </div>
              @else
                <div class="slot-row {{ $hasMine ? 'has-mine' : '' }} {{ $isPast ? 'past' : '' }}"
                  @if (!$isPast)
                    data-slot="{{ $day['date'] }} {{ $hhmm }}"
                    data-taken="{{ implode(',', $cell['taken_device_ids']) }}"
                    data-mine="{{ implode(',', $cell['mine_device_ids'] ?? []) }}"
                  @endif
                  role="button" tabindex="0">
                  <div class="time">{{ $hhmm }}–{{ $slotEnd }}</div>
                  <div class="state">
                    @if (($cell['free_by_type']['single'] ?? 0) > 0)
                      <span class="avail-chip"><i class="fa-solid fa-user"></i>{{ $cell['free_by_type']['single'] }} enkelt</span>
                    @endif
                    @if (($cell['free_by_type']['double'] ?? 0) > 0)
                      <span class="avail-chip"><i class="fa-solid fa-user-group"></i>{{ $cell['free_by_type']['double'] }} dobbelt</span>
                    @endif
                  </div>
                </div>
              @endif
            @endforeach
          @endif
        </div>
      @endforeach
    </div>
  @endif

</div>

<div class="book-backdrop" id="bookBackdrop" role="dialog" aria-modal="true">
  <div class="book-modal">
    <div class="book-head">
      <div class="title">Vælg tank</div>
      <button type="button" class="close" id="bookClose" aria-label="Luk"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="book-body">
      <div class="when" id="bookWhen"></div>
      <div class="device-list" id="bookDevices"></div>
    </div>
    <div class="book-foot">
      <span class="hint">{{ $settings->slot_duration_minutes }} min · pris vises pr. tank</span>
    </div>
  </div>
</div>

@push('scripts')
<script>
(function () {
  var CSRF = document.querySelector('meta[name=csrf-token]').content;
  var BOOK_URL = '{{ route('floating.book') }}';
  @php
    $deviceJson = $devices->map(fn ($d) => [
        'id' => $d->id,
        'name' => $d->name,
        'type_label' => $d->typeLabel(),
        'price_label' => $settings->priceLabelFor($d->type),
    ])->values();
  @endphp
  var DEVICES = @json($deviceJson);
  var backdrop = document.getElementById('bookBackdrop');
  var whenEl = document.getElementById('bookWhen');
  var listEl = document.getElementById('bookDevices');
  var closeBtn = document.getElementById('bookClose');

  function escapeHtml(s) { return String(s ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }

  function open(slot, takenIds, mineIds) {
    var toIntSet = function (csv) {
      return new Set((csv || '').split(',').filter(Boolean).map(function (n) { return parseInt(n, 10); }));
    };
    var taken = toIntSet(takenIds);
    var mine = toIntSet(mineIds);
    var dt = slot.split(' ');
    whenEl.textContent = dt[0] + ' kl. ' + dt[1];
    listEl.innerHTML = DEVICES.map(function (d) {
      var isMine = mine.has(d.id);
      var isTaken = taken.has(d.id) && !isMine;
      var cls = isMine ? 'mine' : (isTaken ? 'taken' : '');
      var trailing = isMine
        ? '<span class="meta">Din booking</span>'
        : (isTaken ? '<span class="meta">Optaget</span>' : '<button class="btn btn-primary btn-sm" type="submit">Book</button>');
      var submitGuard = (isMine || isTaken) ? '' : 'onsubmit="this.submitting=true;"';
      return '<form method="POST" action="' + BOOK_URL + '" class="device-pick ' + cls + '" ' + submitGuard + '>' +
        '<input type="hidden" name="_token" value="' + CSRF + '">' +
        '<input type="hidden" name="device_id" value="' + d.id + '">' +
        '<input type="hidden" name="slot_start" value="' + escapeHtml(slot) + '">' +
        '<span class="nm">' + escapeHtml(d.name) + '</span>' +
        '<span class="meta">' + escapeHtml(d.type_label) + ' · ' + escapeHtml(d.price_label) + '</span>' +
        trailing +
        '</form>';
    }).join('');
    backdrop.classList.add('open');
  }
  function close() { backdrop.classList.remove('open'); }

  document.querySelectorAll('.slot[data-slot], .slot-row[data-slot]').forEach(function (btn) {
    var trigger = function () {
      if (btn.disabled) return;
      open(btn.dataset.slot, btn.dataset.taken, btn.dataset.mine);
    };
    btn.addEventListener('click', trigger);
    btn.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); trigger(); }
    });
  });

  // Mobile day tabs
  document.querySelectorAll('.float-mobile .day-tab').forEach(function (tab) {
    tab.addEventListener('click', function () {
      var day = tab.dataset.day;
      document.querySelectorAll('.float-mobile .day-tab').forEach(function (t) { t.classList.toggle('active', t === tab); });
      document.querySelectorAll('.float-mobile .day-panel').forEach(function (p) { p.classList.toggle('active', p.dataset.day === day); });
    });
  });
  closeBtn.addEventListener('click', close);
  backdrop.addEventListener('click', function (e) { if (e.target === backdrop) close(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && backdrop.classList.contains('open')) close(); });
})();
</script>
@endpush

@endsection
