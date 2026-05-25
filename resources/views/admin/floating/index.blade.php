@extends('layouts.app')
@section('content')

@push('styles')
<style>
  .floating-shell { max-width: 760px; }
  .settings-card { background: #fff; border-radius: 12px; box-shadow: 0 1px 2px rgba(0,0,0,0.08); padding: 18px 20px; margin-bottom: 16px; }
  .settings-card h2 { font-size: 16px; font-weight: 700; margin-bottom: 4px; }
  .settings-card .hint { color: var(--muted); font-size: 13px; margin-bottom: 14px; }
  .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
  .form-row { margin-bottom: 12px; }
  .form-row label { display: block; font-size: 12px; text-transform: uppercase; letter-spacing: 0.4px; color: var(--muted); font-weight: 700; margin-bottom: 6px; }
  .form-row input[type=text], .form-row input[type=number], .form-row input[type=time], .form-row select { width: 100%; padding: 10px 12px; font-size: 14px; border: 1px solid var(--border); border-radius: 8px; background: #fff; }
  .form-row input:focus, .form-row select:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px rgba(24,119,242,0.15); }
  .weekday-row { display: flex; flex-wrap: wrap; gap: 6px; }
  .weekday-chip { cursor: pointer; user-select: none; }
  .weekday-chip input { position: absolute; opacity: 0; pointer-events: none; }
  .weekday-chip span { display: inline-block; padding: 8px 14px; border-radius: 999px; border: 1px solid var(--border); background: #fff; font-size: 13px; }
  .weekday-chip:hover span { background: var(--hover); }
  .weekday-chip input:checked + span { background: var(--accent); border-color: var(--accent); color: #fff; }

  .device-row { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border: 1px solid var(--border); border-radius: 10px; margin-bottom: 8px; background: #fff; flex-wrap: wrap; }
  .device-row input[type=text] { flex: 1; min-width: 140px; padding: 8px 10px; font-size: 14px; border: 1px solid var(--border); border-radius: 6px; }
  .device-row select { padding: 8px 10px; font-size: 14px; border: 1px solid var(--border); border-radius: 6px; background: #fff; }
  .device-row .switch { display: inline-flex; align-items: center; gap: 6px; font-size: 13px; color: var(--muted); }
  .device-row form { display: inline-flex; align-items: center; gap: 8px; margin: 0; }
  .device-add { display: flex; gap: 8px; align-items: center; margin-top: 12px; flex-wrap: wrap; }
  .device-add input { flex: 1; min-width: 140px; padding: 9px 12px; font-size: 14px; border: 1px solid var(--border); border-radius: 8px; }
  .device-add select { padding: 9px 12px; font-size: 14px; border: 1px solid var(--border); border-radius: 8px; background: #fff; }

  @media (max-width: 600px) { .grid-2 { grid-template-columns: 1fr; } }
</style>
@endpush

<div class="view-header">
  <h1>Indstillinger</h1>
  @include('partials.header-actions')
</div>

@include('admin.settings._subnav')

@if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif

<div class="floating-shell">
  <form method="POST" action="{{ route('admin.settings.floating.update') }}" class="settings-card">
    @csrf
    <h2>Floating</h2>
    <div class="hint">Indstil hvor længe et slot varer, åbningstider, pris og afbestillingsfrist. Stripe-priser opdateres automatisk når feltet er konfigureret.</div>

    <div class="grid-2">
      <div class="form-row">
        <label for="slot_duration_minutes">Slot-længde (minutter)</label>
        <select id="slot_duration_minutes" name="slot_duration_minutes">
          @foreach ([15,30,45,60,75,90,120] as $m)
            <option value="{{ $m }}" {{ (int) old('slot_duration_minutes', $settings->slot_duration_minutes) === $m ? 'selected' : '' }}>{{ $m }} min</option>
          @endforeach
        </select>
      </div>
      <div class="form-row">
        <label for="price_kr">Pris pr. slot (kr)</label>
        @php $priceKr = $settings->price_cents / 100; $priceKrDisplay = $priceKr == (int) $priceKr ? (string) (int) $priceKr : rtrim(rtrim(number_format($priceKr, 2, '.', ''), '0'), '.'); @endphp
        <input id="price_kr" type="number" name="price_kr" min="0" step="0.01" value="{{ old('price_kr', $priceKrDisplay) }}">
      </div>
    </div>

    <div class="grid-2">
      <div class="form-row">
        <label for="open_from">Åbent fra</label>
        <input id="open_from" type="time" name="open_from" value="{{ old('open_from', substr((string) $settings->open_from, 0, 5)) }}">
      </div>
      <div class="form-row">
        <label for="open_to">Åbent til</label>
        <input id="open_to" type="time" name="open_to" value="{{ old('open_to', substr((string) $settings->open_to, 0, 5)) }}">
      </div>
    </div>

    <div class="form-row">
      <label>Åbningsdage</label>
      @php $selectedDays = is_array(old('days_open')) ? old('days_open') : $settings->daysList(); @endphp
      <div class="weekday-row">
        @foreach (['mon'=>'Man','tue'=>'Tir','wed'=>'Ons','thu'=>'Tor','fri'=>'Fre','sat'=>'Lør','sun'=>'Søn'] as $code => $name)
          <label class="weekday-chip">
            <input type="checkbox" name="days_open[]" value="{{ $code }}" {{ in_array($code, $selectedDays, true) ? 'checked' : '' }}>
            <span>{{ $name }}</span>
          </label>
        @endforeach
      </div>
    </div>

    <div class="form-row">
      <label for="cancel_cutoff_hours">Afbestillingsfrist (timer før slot)</label>
      <input id="cancel_cutoff_hours" type="number" name="cancel_cutoff_hours" min="0" max="168" value="{{ old('cancel_cutoff_hours', $settings->cancel_cutoff_hours) }}">
    </div>

    @error('open_to')<div class="hint" style="color:var(--danger);">{{ $message }}</div>@enderror

    <button class="btn btn-primary" type="submit">Gem indstillinger</button>
  </form>

  <div class="settings-card">
    <h2>Tanke</h2>
    <div class="hint">Hver tank kan bookes uafhængigt. Inaktive tanke vises ikke i kalenderen.</div>

    @forelse ($devices as $d)
      <form method="POST" action="{{ route('admin.settings.floating.devices.update', $d) }}" class="device-row">
        @csrf
        <input type="text" name="name" value="{{ $d->name }}" required>
        <select name="type" title="Type">
          <option value="single" {{ $d->type === 'single' ? 'selected' : '' }}>Enkelt</option>
          <option value="double" {{ $d->type === 'double' ? 'selected' : '' }}>Dobbelt</option>
        </select>
        <label class="switch" title="Aktiv">
          <input type="checkbox" name="is_active" value="1" {{ $d->is_active ? 'checked' : '' }}>
          <span class="knob"></span>
        </label>
        <button class="btn btn-secondary btn-sm" type="submit">Gem</button>
        <button class="btn btn-ghost btn-sm" type="submit" formaction="{{ route('admin.settings.floating.devices.destroy', $d) }}" onclick="return confirm('Slet {{ $d->name }}? Eksisterende bookinger bevares.');"><i class="fa-solid fa-trash"></i></button>
      </form>
    @empty
      <div class="hint" style="margin:0;">Endnu ingen tanke. Tilføj din første nedenfor.</div>
    @endforelse

    <form method="POST" action="{{ route('admin.settings.floating.devices.store') }}" class="device-add">
      @csrf
      <input type="text" name="name" placeholder="Fx Tank A" required>
      <select name="type">
        <option value="single">Enkelt</option>
        <option value="double">Dobbelt</option>
      </select>
      <button class="btn btn-primary btn-sm" type="submit"><i class="fa-solid fa-plus"></i> Tilføj</button>
    </form>
  </div>
</div>

@endsection
