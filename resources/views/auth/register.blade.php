@extends('layouts.app')
@section('content')
<div style="max-width: 460px; margin: 24px auto;">
  <div class="card card-pad">
    <h1 style="font-size:22px;font-weight:700;margin-bottom:6px;">Create your account</h1>
    <p style="color:var(--muted);margin-bottom:18px;">Browse courses, enroll, and chat with the crew.</p>
    <form method="POST" action="{{ route('register') }}">
      @csrf
      <div class="form-row">
        <label for="name">Name</label>
        <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus>
      </div>
      <div class="form-row">
        <label for="email">Email</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" required>
      </div>
      <div class="form-row">
        <label for="phone">Phone <span style="color:var(--muted);font-weight:400;">(optional)</span></label>
        <input id="phone" type="tel" name="phone" value="{{ old('phone') }}">
      </div>
      <div class="form-row">
        <label for="password">Password</label>
        <input id="password" type="password" name="password" required>
        <div class="hint">At least 8 characters.</div>
      </div>
      <div class="form-row">
        <label for="password_confirmation">Confirm password</label>
        <input id="password_confirmation" type="password" name="password_confirmation" required>
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;">Create account</button>
    </form>
    <div style="margin-top:14px;text-align:center;font-size:13px;color:var(--muted);">
      Already have an account? <a href="{{ route('login') }}" style="color:var(--accent);font-weight:600;">Log in</a>
    </div>
  </div>
</div>
@endsection
