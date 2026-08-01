@extends('layouts.app')
@section('content')
<div style="max-width: 420px;">
  <div class="card card-pad">
    <h1 style="font-size:22px;font-weight:700;margin-bottom:6px;">Glemt adgangskode</h1>
    <p style="color:var(--muted);margin-bottom:18px;">Skriv din e-mail, så sender vi dig et link til at vælge en ny adgangskode.</p>
    <form method="POST" action="{{ route('password.email') }}">
      @csrf
      <div class="form-row">
        <label for="email">E-mail</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;">Send link</button>
    </form>
    <div style="margin-top:14px;text-align:center;font-size:13px;color:var(--muted);">
      Kom du i tanke om den? <a href="{{ route('login') }}" style="color:var(--accent);font-weight:600;">Log ind</a>
    </div>
  </div>
</div>
@endsection
