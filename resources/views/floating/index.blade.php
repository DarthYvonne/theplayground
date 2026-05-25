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
  .float-grid .slot { display: block; border: 1px solid var(--border); background: #fff; border-radius: 8px; padding: 8px 10px; cursor: pointer; transition: background 0.1s, border-color 0.1s; font: inherit; width: 100%; text-align: left; color: inherit; }
  .float-grid .slot:hover { background: var(--accent-soft); border-color: var(--accent); }
  .float-grid .slot.free .count { color: #166534; font-weight: 700; }
  .float-grid .slot.tight .count { color: #b45309; font-weight: 700; }
  .float-grid .slot.full { background: #f5f6f8; border-color: #e7eaee; color: var(--muted); cursor: not-allowed; }
  .float-grid .slot.full:hover { background: #f5f6f8; border-color: #e7eaee; }
  .float-grid .slot.mine { background: #ecfdf5; border-color: #34d399; }
  .float-grid .slot.mine .count { color: #065f46; font-weight: 700; }
  .float-grid .slot.past { opacity: 0.45; cursor: not-allowed; }
  .float-grid .slot .count { font-size: 12px; }
  .float-grid .slot .lbl { display: block; font-weight: 600; }
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
  .book-body .device-pick .nm { font-weight: 600; flex: 1; }
  .book-body .device-pick .meta { color: var(--muted); font-size: 12px; }
  .book-foot { padding: 12px 20px; border-top: 1px solid #f0f2f5; display: flex; gap: 8px; align-items: center; }
  .book-foot .hint { color: var(--muted); font-size: 12px; flex: 1; }

  /* My upcoming */
  .my-up { margin-top: 18px; background: #fff; border-radius: 12px; padding: 14px 16px; box-shadow: 0 1px 2px rgba(0,0,0,0.08); }
  .my-up h2 { font-size: 14px; font-weight: 700; margin-bottom: 10px; }
  .my-up .row { display: flex; align-items: center; gap: 10px; padding: 8px 0; border-bottom: 1px solid #f0f2f5; }
  .my-up .row:last-child { border-bottom: none; }
  .my-up .row .meta { flex: 1; }
  .my-up .row .when { font-weight: 600; }
  .my-up .row .sub { color: var(--muted); font-size: 12px; }
  .my-up .row .actions { display: inline-flex; gap: 6px; }

  @media (max-width: 767px) {
    .float-grid { font-size: 12px; }
    .float-grid thead th { padding: 8px 4px; font-size: 11px; }
    .float-grid tbody th { font-size: 11px; padding: 6px 6px; min-width: 50px; }
    .float-grid .slot { padding: 6px 6px; font-size: 11px; }
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
            <tr>
              <th>{{ $hhmm }}</th>
              @foreach ($days as $day)
                @php
                  $cell = $grid[$day['date']][$hhmm] ?? null;
                  $slotStart = \Carbon\Carbon::parse($day['date'] . ' ' . $hhmm);
                  $isPast = $slotStart->isPast();
                @endphp
                @if (!$day['is_open'])
                  <td class="closed">@if ($loop->first && $hhmm === ($slots[0] ?? null))Lukket @endif</td>
                @elseif ($cell['mine'] ?? false)
                  <td class="cell">
                    <button type="button" class="slot mine" disabled>
                      <span class="lbl">Din booking</span>
                      <span class="count">{{ $cell['mine']->device->name ?? 'Tank' }}</span>
                    </button>
                  </td>
                @elseif ($cell['free_count'] <= 0)
                  <td class="cell">
                    <button type="button" class="slot full" disabled>
                      <span class="lbl">Optaget</span>
                      <span class="count">0 af {{ $devices->count() }}</span>
                    </button>
                  </td>
                @else
                  <td class="cell">
                    <button type="button"
                      class="slot {{ $cell['free_count'] === $devices->count() ? 'free' : 'tight' }} {{ $isPast ? 'past' : '' }}"
                      {{ $isPast ? 'disabled' : '' }}
                      data-slot="{{ $day['date'] }} {{ $hhmm }}"
                      data-taken="{{ implode(',', $cell['taken_device_ids']) }}"
                    >
                      <span class="lbl">Book</span>
                      <span class="count">{{ $cell['free_count'] }} af {{ $devices->count() }} ledige</span>
                    </button>
                  </td>
                @endif
              @endforeach
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif

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

  function open(slot, takenIds) {
    var taken = new Set((takenIds || '').split(',').filter(Boolean).map(function (n) { return parseInt(n, 10); }));
    var dt = slot.split(' ');
    whenEl.textContent = dt[0] + ' kl. ' + dt[1];
    listEl.innerHTML = DEVICES.map(function (d) {
      var isTaken = taken.has(d.id);
      return '<form method="POST" action="' + BOOK_URL + '" class="device-pick ' + (isTaken ? 'taken' : '') + '" ' + (isTaken ? '' : 'onsubmit="this.submitting=true;"') + '>' +
        '<input type="hidden" name="_token" value="' + CSRF + '">' +
        '<input type="hidden" name="device_id" value="' + d.id + '">' +
        '<input type="hidden" name="slot_start" value="' + escapeHtml(slot) + '">' +
        '<span class="nm">' + escapeHtml(d.name) + '</span>' +
        '<span class="meta">' + escapeHtml(d.type_label) + ' · ' + escapeHtml(d.price_label) + '</span>' +
        (isTaken ? '<span class="meta">Optaget</span>' : '<button class="btn btn-primary btn-sm" type="submit">Book</button>') +
        '</form>';
    }).join('');
    backdrop.classList.add('open');
  }
  function close() { backdrop.classList.remove('open'); }

  document.querySelectorAll('.slot[data-slot]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      if (btn.disabled) return;
      open(btn.dataset.slot, btn.dataset.taken);
    });
  });
  closeBtn.addEventListener('click', close);
  backdrop.addEventListener('click', function (e) { if (e.target === backdrop) close(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && backdrop.classList.contains('open')) close(); });
})();
</script>
@endpush

@endsection
