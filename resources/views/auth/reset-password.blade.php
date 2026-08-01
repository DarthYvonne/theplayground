@extends('layouts.app')
@section('content')
<div style="max-width: 420px;">
  <div class="card card-pad">
    <h1 style="font-size:22px;font-weight:700;margin-bottom:6px;">Vælg ny adgangskode</h1>
    <p style="color:var(--muted);margin-bottom:18px;">Adgangskoden skal være mindst 8 tegn.</p>
    <form method="POST" action="{{ route('password.update') }}">
      @csrf
      <input type="hidden" name="token" value="{{ $token }}">
      <div class="form-row">
        <label for="email">E-mail</label>
        <input id="email" type="email" name="email" value="{{ old('email', $email) }}" required>
      </div>
      <div class="form-row">
        <label for="password">Ny adgangskode</label>
        <input id="password" type="password" name="password" required autofocus autocomplete="new-password">
      </div>
      <div class="form-row">
        <label for="password_confirmation">Gentag ny adgangskode</label>
        <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;">Gem adgangskode</button>
    </form>
    <div style="margin-top:14px;text-align:center;font-size:13px;color:var(--muted);">
      <a href="{{ route('password.request') }}" style="color:var(--accent);font-weight:600;">Bed om et nyt link</a>
    </div>
  </div>
</div>
@endsection
