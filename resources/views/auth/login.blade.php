@extends('layouts.app')
@section('content')
<div style="max-width: 420px; margin: 24px auto;">
  <div class="card card-pad">
    <h1 style="font-size:22px;font-weight:700;margin-bottom:6px;">Log in</h1>
    <p style="color:var(--muted);margin-bottom:18px;">Welcome back to The Playground.</p>
    <form method="POST" action="{{ route('login') }}">
      @csrf
      <div class="form-row">
        <label for="email">Email</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>
      </div>
      <div class="form-row">
        <label for="password">Password</label>
        <input id="password" type="password" name="password" required>
      </div>
      <div class="form-row">
        <label class="switch"><input type="checkbox" name="remember" value="1"><span class="knob"></span><span>Remember me</span></label>
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;">Log in</button>
    </form>
    <div style="margin-top:14px;text-align:center;font-size:13px;color:var(--muted);">
      New here? <a href="{{ route('register') }}" style="color:var(--accent);font-weight:600;">Create an account</a>
    </div>
  </div>
</div>
@endsection
