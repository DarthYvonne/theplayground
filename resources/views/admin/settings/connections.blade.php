@extends('layouts.app')
@section('content')

<div class="view-header">
  <h1>Indstillinger</h1>
  @include('partials.header-actions')
</div>

@include('admin.settings._subnav')

<div>
  <div class="card">
    <div style="padding: 16px 18px; border-bottom: 1px solid #f0f2f5; display:flex; gap:14px; align-items:center;">
      <div style="width:48px;height:48px;border-radius:12px;background:#635bff;color:#fff;display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0;">
        <i class="fa-brands fa-stripe-s"></i>
      </div>
      <div style="flex:1;min-width:0;">
        <div style="font-weight:700;font-size:16px;">Stripe</div>
        <div style="color:var(--muted);font-size:13px;margin-top:2px;">Bruges til månedlige abonnementer på hold. Hent dine nøgler på <a href="https://dashboard.stripe.com/apikeys" target="_blank" rel="noopener" style="color:var(--accent);">dashboard.stripe.com/apikeys</a>.</div>
      </div>
      <div>
        @if ($stripe['configured'])<span class="tag success"><i class="fa-solid fa-circle-check"></i> Forbundet</span>
        @else<span class="tag muted"><i class="fa-solid fa-circle-dot"></i> Ikke konfigureret</span>@endif
      </div>
    </div>

    <form method="POST" action="{{ route('admin.settings.update') }}" style="padding: 16px 18px;">
      @csrf

      <div class="form-row">
        <label for="stripe_key">Publishable key <span style="color:var(--muted);font-weight:400;">(starter med <code>pk_test_</code> eller <code>pk_live_</code>)</span></label>
        <input id="stripe_key" type="text" name="stripe_key" value="{{ old('stripe_key', $stripe['key']) }}" placeholder="pk_test_…" spellcheck="false" autocomplete="off">
        <div class="hint">Sikker at vise i browseren.</div>
      </div>

      <div class="form-row">
        <label for="stripe_secret">Hemmelig nøgle <span style="color:var(--muted);font-weight:400;">(starter med <code>sk_test_</code> eller <code>sk_live_</code>)</span></label>
        <div style="display:flex;gap:8px;align-items:center;">
          <input id="stripe_secret" type="password" name="stripe_secret" value="" placeholder="{{ $stripe['secret_set'] ? '•••• gemt — lad være tom for at beholde ••••' : 'sk_test_…' }}" spellcheck="false" autocomplete="off" style="flex:1;">
          <button type="button" class="btn btn-ghost btn-sm" onclick="togglePw('stripe_secret', this)" aria-label="Vis/skjul"><i class="fa-regular fa-eye"></i></button>
        </div>
        @if ($stripe['secret_set'])
          <label class="switch" style="margin-top:8px;font-weight:400;font-size:13px;">
            <input type="checkbox" name="clear_secret" value="1">
            <span class="knob"></span>
            <span>Fjern den gemte hemmelige nøgle</span>
          </label>
        @endif
        <div class="hint">Gemmes krypteret i databasen. Vises aldrig i browseren og logges ikke.</div>
      </div>

      <div class="form-row">
        <label for="stripe_webhook_secret">Webhook-signatur <span style="color:var(--muted);font-weight:400;">(starter med <code>whsec_</code>)</span></label>
        <div style="display:flex;gap:8px;align-items:center;">
          <input id="stripe_webhook_secret" type="password" name="stripe_webhook_secret" value="" placeholder="{{ $stripe['webhook_set'] ? '•••• gemt — lad være tom for at beholde ••••' : 'whsec_…' }}" spellcheck="false" autocomplete="off" style="flex:1;">
          <button type="button" class="btn btn-ghost btn-sm" onclick="togglePw('stripe_webhook_secret', this)" aria-label="Vis/skjul"><i class="fa-regular fa-eye"></i></button>
        </div>
        @if ($stripe['webhook_set'])
          <label class="switch" style="margin-top:8px;font-weight:400;font-size:13px;">
            <input type="checkbox" name="clear_webhook" value="1">
            <span class="knob"></span>
            <span>Fjern den gemte webhook-signatur</span>
          </label>
        @endif
        <div class="hint">Find denne på dit endpoint i <code>dashboard.stripe.com/webhooks</code>. Bruges til at verificere indkommende Stripe-events.</div>
      </div>

      <div class="form-row">
        <label for="stripe_currency">Valuta</label>
        <select id="stripe_currency" name="stripe_currency" style="max-width: 220px;">
          @foreach (['dkk' => 'DKK — Danske kroner', 'eur' => 'EUR — Euro', 'usd' => 'USD — Amerikanske dollar', 'gbp' => 'GBP — Britiske pund', 'sek' => 'SEK — Svenske kroner', 'nok' => 'NOK — Norske kroner'] as $code => $label)
            <option value="{{ $code }}" {{ $stripe['currency'] === $code ? 'selected' : '' }}>{{ $label }}</option>
          @endforeach
        </select>
      </div>

      <div style="display:flex;gap:8px;flex-wrap:wrap;border-top:1px solid #f0f2f5;padding-top:14px;margin-top:6px;">
        <button class="btn btn-primary" type="submit">Gem indstillinger</button>
        @if ($stripe['configured'])
          <button class="btn btn-secondary" type="submit" formaction="{{ route('admin.settings.test') }}">
            <i class="fa-solid fa-plug-circle-check"></i> Test forbindelse
          </button>
        @endif
      </div>
    </form>
  </div>

  <div class="card card-pad" style="background:var(--accent-soft);border:1px solid #cfddf8;">
    <div style="display:flex;gap:12px;align-items:flex-start;">
      <i class="fa-solid fa-circle-info" style="color:var(--accent);font-size:18px;margin-top:2px;"></i>
      <div style="font-size:13px;line-height:1.5;color:#1a3c7a;">
        <strong>Bemærk:</strong> indtil Stripe-nøgler er gemt her (eller sat i <code>.env</code>), bliver tilmeldinger registreret uden betaling, så resten af appen kan testes. Når nøglerne er på plads, sendes tilmelding videre til Stripe Checkout og produkt + månedlig pris oprettes automatisk på Stripe, når du gemmer et hold.
      </div>
    </div>
  </div>
</div>

@push('scripts')
<script>
function togglePw(id, btn) {
  var el = document.getElementById(id);
  if (!el) return;
  if (el.type === 'password') { el.type = 'text'; btn.innerHTML = '<i class="fa-regular fa-eye-slash"></i>'; }
  else { el.type = 'password'; btn.innerHTML = '<i class="fa-regular fa-eye"></i>'; }
}
</script>
@endpush

@endsection
