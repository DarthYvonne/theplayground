@extends('layouts.app')
@section('content')
<div style="max-width: 460px;">
  <div class="card card-pad">
    <h1 style="font-size:22px;font-weight:700;margin-bottom:6px;">Opret din konto</h1>
    <p style="color:var(--muted);margin-bottom:18px;">Find hold, tilmeld dig og chat med holdet.</p>
    <form method="POST" action="{{ route('register') }}">
      @csrf
      <div class="form-row">
        <label for="name">Navn</label>
        <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus>
      </div>
      <div class="form-row">
        <label for="email">E-mail</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" required>
      </div>
      <div class="form-row">
        <label for="phone">Telefon <span style="color:var(--muted);font-weight:400;">(valgfrit)</span></label>
        <input id="phone" type="tel" name="phone" value="{{ old('phone') }}">
      </div>
      <div class="form-row">
        <label for="password">Adgangskode</label>
        <input id="password" type="password" name="password" required>
        <div class="hint">Mindst 8 tegn.</div>
      </div>
      <div class="form-row">
        <label for="password_confirmation">Bekræft adgangskode</label>
        <input id="password_confirmation" type="password" name="password_confirmation" required>
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;">Opret konto</button>
    </form>
    <div style="margin-top:14px;text-align:center;font-size:13px;color:var(--muted);">
      Har du allerede en konto? <a href="{{ route('login') }}" style="color:var(--accent);font-weight:600;">Log ind</a>
    </div>
  </div>
</div>
@endsection
