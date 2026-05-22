@extends('layouts.app')
@section('content')
<div style="max-width: 420px;">
  <div class="card card-pad">
    <h1 style="font-size:22px;font-weight:700;margin-bottom:6px;">Log ind</h1>
    <p style="color:var(--muted);margin-bottom:18px;">Velkommen tilbage til The Playground.</p>
    <form method="POST" action="{{ route('login') }}">
      @csrf
      <div class="form-row">
        <label for="email">E-mail</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>
      </div>
      <div class="form-row">
        <label for="password">Adgangskode</label>
        <input id="password" type="password" name="password" required>
      </div>
      <div class="form-row">
        <label class="switch"><input type="checkbox" name="remember" value="1"><span class="knob"></span><span>Husk mig</span></label>
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;">Log ind</button>
    </form>
    <div style="margin-top:14px;text-align:center;font-size:13px;color:var(--muted);">
      Ny her? <a href="{{ route('register') }}" style="color:var(--accent);font-weight:600;">Opret en konto</a>
    </div>
  </div>
</div>
@endsection
