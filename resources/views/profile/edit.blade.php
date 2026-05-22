@extends('layouts.app')
@section('content')

<div class="view-header">
  <h1>My profile</h1>
  @include('partials.header-actions')
</div>

<div style="max-width: 620px; margin: 0 auto;">
  <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="card card-pad">
    @csrf
    <div style="display:flex;gap:16px;align-items:center;margin-bottom:18px;">
      @include('partials.avatar', ['u' => $user, 'size' => 'xl'])
      <div>
        <div style="font-weight:700;font-size:16px;">{{ $user->name }}</div>
        <div style="color:var(--muted);font-size:13px;">{{ ucfirst($user->role) }}</div>
      </div>
    </div>

    <div class="form-row">
      <label for="picture">Profile picture</label>
      <input id="picture" type="file" name="picture" accept="image/*">
      <div class="hint">JPG/PNG, up to 4 MB.</div>
    </div>
    <div class="form-row">
      <label for="name">Name</label>
      <input id="name" type="text" name="name" value="{{ old('name', $user->name) }}" required>
    </div>
    <div class="form-row">
      <label for="email">Email</label>
      <input id="email" type="email" name="email" value="{{ old('email', $user->email) }}" required>
    </div>
    <div class="form-row">
      <label for="phone">Phone</label>
      <input id="phone" type="tel" name="phone" value="{{ old('phone', $user->phone) }}">
    </div>
    <div class="form-row">
      <label for="about">About me</label>
      <textarea id="about" name="about" rows="4" placeholder="Tell people a bit about yourself…">{{ old('about', $user->about) }}</textarea>
    </div>

    <div style="border-top:1px solid #f0f2f5;padding-top:16px;margin-top:8px;">
      <div style="font-weight:700;margin-bottom:10px;">Change password</div>
      <div class="form-row">
        <label for="password">New password</label>
        <input id="password" type="password" name="password">
        <div class="hint">Leave blank to keep current password.</div>
      </div>
      <div class="form-row">
        <label for="password_confirmation">Confirm new password</label>
        <input id="password_confirmation" type="password" name="password_confirmation">
      </div>
    </div>

    <button type="submit" class="btn btn-primary">Save changes</button>
  </form>
</div>
@endsection
