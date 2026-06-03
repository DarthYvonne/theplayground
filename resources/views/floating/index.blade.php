@extends('layouts.app')
@section('content')

@push('styles')
<style>
  .float-shell { max-width: 1100px; }

  /* My upcoming */
  .my-up { margin-bottom: 18px; background: #fff; border-radius: 12px; padding: 14px 16px; box-shadow: 0 1px 2px rgba(0,0,0,0.08); }
  .my-up h2 { font-size: 14px; font-weight: 700; margin-bottom: 10px; }
  .my-up .row { display: flex; align-items: center; gap: 10px; padding: 8px 0; border-bottom: 1px solid #f0f2f5; }
  .my-up .row:last-child { border-bottom: none; }
  .my-up .row .meta { flex: 1; }
  .my-up .row .when { font-weight: 600; }
  .my-up .row .sub { color: var(--muted); font-size: 12px; }
  .my-up .row .actions { display: inline-flex; gap: 6px; }

  .float-empty { background: #fff; border-radius: 12px; padding: 40px 20px; text-align: center; color: var(--muted); box-shadow: 0 1px 2px rgba(0,0,0,0.08); }
  .float-empty h3 { color: var(--text); margin-bottom: 6px; }

  /* Tank cards */
  .float-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 18px; align-items: start; }
  .float-card { background: #fff; border-radius: 14px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); overflow: hidden; display: flex; flex-direction: column; }
  .float-card .photo { position: relative; aspect-ratio: 16 / 10; background: #e9edf1; }
  .float-card .photo img { width: 100%; height: 100%; object-fit: cover; display: block; }
  .float-card .photo .badges { position: absolute; left: 12px; bottom: 12px; right: 12px; display: flex; align-items: flex-end; justify-content: space-between; gap: 8px; }
  .float-card .photo .nm { color: #fff; font-weight: 800; font-size: 18px; text-shadow: 0 1px 6px rgba(0,0,0,0.55); line-height: 1.15; }
  .float-card .photo .nm .ty { display: block; font-size: 12px; font-weight: 600; opacity: 0.92; text-shadow: 0 1px 4px rgba(0,0,0,0.55); }
  .float-card .photo .price { background: rgba(255,255,255,0.95); color: var(--accent); padding: 5px 11px; border-radius: 999px; font-weight: 700; font-size: 13px; white-space: nowrap; }

  /* Day nav header inside a card */
  .float-card .daynav { display: flex; align-items: center; gap: 6px; padding: 10px 12px; border-bottom: 1px solid #f0f2f5; }
  .float-card .daynav .nav-prev, .float-card .daynav .nav-next { background: #fff; border: 1px solid var(--border); color: var(--muted); width: 32px; height: 32px; border-radius: 8px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; font-size: 13px; flex: 0 0 auto; }
  .float-card .daynav .nav-prev:hover, .float-card .daynav .nav-next:hover { background: var(--accent-soft); border-color: var(--accent); color: var(--accent); }
  .float-card .daynav .picker { flex: 1; display: flex; justify-content: center; position: relative; min-width: 0; }
  .float-card .daynav .day-pick { background: none; border: none; cursor: pointer; font: inherit; font-weight: 700; font-size: 13px; color: var(--text); padding: 6px 10px; border-radius: 8px; display: inline-flex; align-items: center; gap: 7px; max-width: 100%; }
  .float-card .daynav .day-pick:hover { background: var(--hover); }
  .float-card .daynav .day-pick .txt { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .float-card .daynav .day-pick .txt.today { color: var(--accent); }
  .float-card .daynav .day-pick i { color: var(--muted); font-size: 12px; flex: 0 0 auto; }
  .float-card .daynav .date-input { position: absolute; left: 50%; bottom: 0; width: 1px; height: 1px; opacity: 0; border: 0; padding: 0; pointer-events: none; }

  /* Day plan */
  .day-plan { padding: 12px; min-height: 86px; }
  .day-plan .loading, .day-plan .none { padding: 24px 10px; text-align: center; color: var(--muted); font-size: 13px; }
  .day-plan .chips { display: flex; flex-wrap: wrap; gap: 6px; }
  .day-plan .chip { border: 1px solid var(--border); background: #fff; border-radius: 8px; padding: 6px 10px; font: inherit; font-size: 12px; font-weight: 600; color: var(--text); cursor: pointer; transition: background 0.1s, border-color 0.1s; }
  .day-plan .chip:hover { background: var(--accent-soft); border-color: var(--accent); color: var(--accent); }
  .day-plan .chip.mine { background: #ecfdf5; border-color: #34d399; color: #065f46; cursor: default; display: inline-flex; align-items: center; gap: 5px; }
  .day-plan .chip.mine:hover { background: #ecfdf5; border-color: #34d399; color: #065f46; }
  .day-plan .chip.mine i { font-size: 10px; color: #16a34a; }

  @media (max-width: 767px) {
    .float-cards { grid-template-columns: 1fr; }
  }
</style>
@endpush

<div class="view-header">
  <h1><i class="fa-solid fa-water" style="color:var(--accent);margin-right:8px;"></i>Floating</h1>
  @include('partials.header-actions')
</div>

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
              @php
                $refundable = $isOwner || $b->isCancellable($settings->cancel_cutoff_hours);
                $wasPaid = $b->paid_at && $b->stripe_payment_intent_id;
                $confirmMsg = $refundable
                  ? ($wasPaid ? 'Aflys denne booking? Betalingen refunderes fuldt.' : 'Aflys denne booking?')
                  : 'Afbestillingsfristen er passeret. Du kan aflyse, men betalingen refunderes ikke. Fortsæt?';
              @endphp
              <form method="POST" action="{{ route('floating.cancel', $b) }}" onsubmit="return confirm(@json($confirmMsg));">
                @csrf
                <button class="btn btn-ghost btn-sm" type="submit">
                  <i class="fa-solid fa-xmark"></i>
                  Aflys
                  @if (!$refundable)
                    <span style="color:var(--muted);font-weight:400;">(ingen refundering)</span>
                  @endif
                </button>
              </form>
            </div>
          </div>
        @endforeach
      </div>
    @endif
  @endauth

  @if ($devices->isEmpty())
    <div class="float-empty">
      <h3>Floating er ikke konfigureret endnu</h3>
      <p>@if ($isOwner)Gå til <a href="{{ route('admin.settings.floating') }}">Indstillinger → Floating</a> for at oprette tanke og sætte åbningstider.@else Kontakt The Playground for at høre mere.@endif</p>
    </div>
  @else
    <div class="float-cards">
      @foreach ($devices as $device)
        @php $img = $device->type === 'double' ? 'img/floating-tank-double.jpg' : 'img/floating-tank.jpg'; @endphp
        <div class="float-card" data-device-id="{{ $device->id }}">
          <div class="photo">
            <img src="{{ asset($img) }}" alt="{{ $device->name }}" loading="lazy">
            <div class="badges">
              <span class="nm">{{ $device->name }}<span class="ty">{{ $device->typeLabel() }} · {{ $settings->slot_duration_minutes }} min</span></span>
              <span class="price">{{ $settings->priceLabelFor($device->type) }}</span>
            </div>
          </div>
          <div class="daynav">
            <button type="button" class="nav-prev" aria-label="Forrige dag"><i class="fa-solid fa-chevron-left"></i></button>
            <span class="picker">
              <button type="button" class="day-pick" aria-label="Vælg dato"><span class="txt">…</span><i class="fa-regular fa-calendar"></i></button>
              <input type="date" class="date-input" min="{{ now()->toDateString() }}" tabindex="-1" aria-hidden="true">
            </span>
            <button type="button" class="nav-next" aria-label="Næste dag"><i class="fa-solid fa-chevron-right"></i></button>
          </div>
          <div class="day-plan"><div class="loading">Indlæser…</div></div>
        </div>
      @endforeach
    </div>
  @endif
</div>

@push('scripts')
<script>
(function () {
  var CSRF = document.querySelector('meta[name=csrf-token]').content;
  var AVAIL_URL = '{{ route('floating.availability') }}';
  var BOOK_URL = '{{ route('floating.book') }}';
  var TODAY = '{{ now()->toDateString() }}';

  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (m) { return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[m]; }); }

  function pad(n) { return String(n).length < 2 ? '0' + n : String(n); }
  function shiftDate(iso, delta) {
    var p = iso.split('-');
    var d = new Date(+p[0], +p[1] - 1, +p[2]);
    d.setDate(d.getDate() + delta);
    return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
  }

  function book(deviceId, slotStart) {
    var f = document.createElement('form');
    f.method = 'POST';
    f.action = BOOK_URL;
    f.innerHTML = '<input type="hidden" name="_token" value="' + esc(CSRF) + '">' +
      '<input type="hidden" name="device_id" value="' + esc(deviceId) + '">' +
      '<input type="hidden" name="slot_start" value="' + esc(slotStart) + '">';
    document.body.appendChild(f);
    f.submit();
  }

  document.querySelectorAll('.float-card[data-device-id]').forEach(function (card) {
    var days = {}; // 'YYYY-MM-DD' -> day object, filled one fetched week at a time
    var plan = card.querySelector('.day-plan');
    var labelTxt = card.querySelector('.day-pick .txt');
    var dateInput = card.querySelector('.date-input');
    var current = TODAY;

    function renderDay(day) {
      labelTxt.textContent = (day.is_today ? 'I dag' : day.label) + ' · ' + day.short;
      labelTxt.classList.toggle('today', !!day.is_today);
      dateInput.value = day.date;
      var bookable = (day.slots || []).filter(function (s) { return s.status === 'free' || s.status === 'mine'; });
      var html;
      if (!day.is_open) {
        html = '<div class="none">Lukket</div>';
      } else if (bookable.length === 0) {
        html = '<div class="none">Ingen ledige tider</div>';
      } else {
        html = '<div class="chips">';
        bookable.forEach(function (s) {
          if (s.status === 'mine') {
            html += '<span class="chip mine" title="Din booking"><i class="fa-solid fa-circle-check"></i>' + esc(s.start) + '–' + esc(s.end) + '</span>';
          } else {
            html += '<button type="button" class="chip" data-slot="' + esc(s.slot_start) + '">' + esc(s.start) + '–' + esc(s.end) + '</button>';
          }
        });
        html += '</div>';
      }
      plan.innerHTML = html;
      plan.querySelectorAll('.chip[data-slot]').forEach(function (btn) {
        btn.addEventListener('click', function () { book(card.dataset.deviceId, btn.dataset.slot); });
      });
    }

    function show(date) {
      current = date;
      if (days[date]) { renderDay(days[date]); return; }
      plan.innerHTML = '<div class="loading">Indlæser…</div>';
      var url = AVAIL_URL + '?device_id=' + encodeURIComponent(card.dataset.deviceId) + '&date=' + encodeURIComponent(date);
      fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { if (!r.ok) throw new Error('http ' + r.status); return r.json(); })
        .then(function (data) {
          data.days.forEach(function (d) { days[d.date] = d; });
          if (days[current]) renderDay(days[current]);
        })
        .catch(function () { plan.innerHTML = '<div class="loading">Kunne ikke hente tider. Prøv igen.</div>'; });
    }

    card.querySelector('.nav-prev').addEventListener('click', function () { show(shiftDate(current, -1)); });
    card.querySelector('.nav-next').addEventListener('click', function () { show(shiftDate(current, 1)); });
    card.querySelector('.day-pick').addEventListener('click', function () {
      if (typeof dateInput.showPicker === 'function') {
        try { dateInput.showPicker(); return; } catch (e) { /* fall through */ }
      }
      dateInput.click();
    });
    dateInput.addEventListener('change', function () { if (dateInput.value) show(dateInput.value); });

    show(current);
  });
})();
</script>
@endpush

@endsection
