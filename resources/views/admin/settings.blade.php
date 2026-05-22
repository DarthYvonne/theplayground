@extends('layouts.app')
@section('content')

<div class="view-header">
  <h1>Settings</h1>
  @include('partials.header-actions')
</div>

<div style="max-width: 760px; margin: 0 auto;">
  <div class="card">
    <div style="padding: 16px 18px; border-bottom: 1px solid #f0f2f5; display:flex; gap:14px; align-items:center;">
      <div style="width:48px;height:48px;border-radius:12px;background:#635bff;color:#fff;display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0;">
        <i class="fa-brands fa-stripe-s"></i>
      </div>
      <div style="flex:1;min-width:0;">
        <div style="font-weight:700;font-size:16px;">Stripe</div>
        <div style="color:var(--muted);font-size:13px;margin-top:2px;">Used for monthly course subscriptions. Find your keys at <a href="https://dashboard.stripe.com/apikeys" target="_blank" rel="noopener" style="color:var(--accent);">dashboard.stripe.com/apikeys</a>.</div>
      </div>
      <div>
        @if ($stripe['configured'])<span class="tag success"><i class="fa-solid fa-circle-check"></i> Connected</span>
        @else<span class="tag muted"><i class="fa-solid fa-circle-dot"></i> Not configured</span>@endif
      </div>
    </div>

    <form method="POST" action="{{ route('admin.settings.update') }}" style="padding: 16px 18px;">
      @csrf

      <div class="form-row">
        <label for="stripe_key">Publishable key <span style="color:var(--muted);font-weight:400;">(starts with <code>pk_test_</code> or <code>pk_live_</code>)</span></label>
        <input id="stripe_key" type="text" name="stripe_key" value="{{ old('stripe_key', $stripe['key']) }}" placeholder="pk_test_…" spellcheck="false" autocomplete="off">
        <div class="hint">Safe to expose to the browser.</div>
      </div>

      <div class="form-row">
        <label for="stripe_secret">Secret key <span style="color:var(--muted);font-weight:400;">(starts with <code>sk_test_</code> or <code>sk_live_</code>)</span></label>
        <div style="display:flex;gap:8px;align-items:center;">
          <input id="stripe_secret" type="password" name="stripe_secret" value="" placeholder="{{ $stripe['secret_set'] ? '•••• stored — leave blank to keep ••••' : 'sk_test_…' }}" spellcheck="false" autocomplete="off" style="flex:1;">
          <button type="button" class="btn btn-ghost btn-sm" onclick="togglePw('stripe_secret', this)" aria-label="Show/hide"><i class="fa-regular fa-eye"></i></button>
        </div>
        @if ($stripe['secret_set'])
          <label class="switch" style="margin-top:8px;font-weight:400;font-size:13px;">
            <input type="checkbox" name="clear_secret" value="1">
            <span class="knob"></span>
            <span>Remove stored secret key</span>
          </label>
        @endif
        <div class="hint">Stored encrypted in the database. Never logged or sent to the browser.</div>
      </div>

      <div class="form-row">
        <label for="stripe_webhook_secret">Webhook signing secret <span style="color:var(--muted);font-weight:400;">(starts with <code>whsec_</code>)</span></label>
        <div style="display:flex;gap:8px;align-items:center;">
          <input id="stripe_webhook_secret" type="password" name="stripe_webhook_secret" value="" placeholder="{{ $stripe['webhook_set'] ? '•••• stored — leave blank to keep ••••' : 'whsec_…' }}" spellcheck="false" autocomplete="off" style="flex:1;">
          <button type="button" class="btn btn-ghost btn-sm" onclick="togglePw('stripe_webhook_secret', this)" aria-label="Show/hide"><i class="fa-regular fa-eye"></i></button>
        </div>
        @if ($stripe['webhook_set'])
          <label class="switch" style="margin-top:8px;font-weight:400;font-size:13px;">
            <input type="checkbox" name="clear_webhook" value="1">
            <span class="knob"></span>
            <span>Remove stored webhook secret</span>
          </label>
        @endif
        <div class="hint">Get this from your endpoint at <code>dashboard.stripe.com/webhooks</code>. Used to verify incoming Stripe events.</div>
      </div>

      <div class="form-row">
        <label for="stripe_currency">Currency</label>
        <select id="stripe_currency" name="stripe_currency" style="max-width: 200px;">
          @foreach (['dkk' => 'DKK — Danish krone', 'eur' => 'EUR — Euro', 'usd' => 'USD — US dollar', 'gbp' => 'GBP — British pound', 'sek' => 'SEK — Swedish krona', 'nok' => 'NOK — Norwegian krone'] as $code => $label)
            <option value="{{ $code }}" {{ $stripe['currency'] === $code ? 'selected' : '' }}>{{ $label }}</option>
          @endforeach
        </select>
      </div>

      <div style="display:flex;gap:8px;flex-wrap:wrap;border-top:1px solid #f0f2f5;padding-top:14px;margin-top:6px;">
        <button class="btn btn-primary" type="submit">Save settings</button>
        @if ($stripe['configured'])
          <button class="btn btn-secondary" type="submit" formaction="{{ route('admin.settings.test') }}">
            <i class="fa-solid fa-plug-circle-check"></i> Test connection
          </button>
        @endif
      </div>
    </form>
  </div>

  <div class="card card-pad" style="background:var(--accent-soft);border:1px solid #cfddf8;">
    <div style="display:flex;gap:12px;align-items:flex-start;">
      <i class="fa-solid fa-circle-info" style="color:var(--accent);font-size:18px;margin-top:2px;"></i>
      <div style="font-size:13px;line-height:1.5;color:#1a3c7a;">
        <strong>Note:</strong> until Stripe keys are saved here (or set in <code>.env</code>), enrollments are recorded directly without payment so the rest of the app stays testable. Once keys are present, the enroll button will redirect to Stripe Checkout (Cashier setup required — see <code>app/Support/StripeConfig.php</code>).
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
