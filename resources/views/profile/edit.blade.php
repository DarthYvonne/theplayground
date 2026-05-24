@extends('layouts.app')
@section('content')

<div class="view-header">
  <h1>Min profil</h1>
  @include('partials.header-actions')
</div>

@include('profile._subnav')

<div>
  <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="card card-pad">
    @csrf
    <div style="display:flex;gap:16px;align-items:center;margin-bottom:18px;">
      @include('partials.avatar', ['u' => $user, 'size' => 'xl'])
      <div>
        <div style="font-weight:700;font-size:16px;">{{ $user->name }}</div>
        <div style="color:var(--muted);font-size:13px;">{{ ['owner' => 'Ejer', 'trainer' => 'Træner', 'user' => 'Bruger'][$user->role] ?? $user->role }}</div>
      </div>
    </div>

    <div class="form-row">
      <label for="picture">Profilbillede</label>
      <input id="picture" type="file" name="picture" accept="image/*">
      <div class="hint">JPG/PNG, op til 16 MB.</div>
    </div>
    <div class="form-row">
      <label for="name">Navn</label>
      <input id="name" type="text" name="name" value="{{ old('name', $user->name) }}" required>
    </div>
    <div class="form-row">
      <label for="email">E-mail</label>
      <input id="email" type="email" name="email" value="{{ old('email', $user->email) }}" required>
    </div>
    <div class="form-row">
      <label for="phone">Telefon</label>
      <input id="phone" type="tel" name="phone" value="{{ old('phone', $user->phone) }}">
    </div>
    <div class="form-row">
      <label for="about">Om mig</label>
      <textarea id="about" name="about" rows="4" placeholder="Fortæl lidt om dig selv…">{{ old('about', $user->about) }}</textarea>
    </div>

    <div style="border-top:1px solid #f0f2f5;padding-top:16px;margin-top:8px;">
      <div style="font-weight:700;margin-bottom:10px;">Skift adgangskode</div>
      <div class="form-row">
        <label for="password">Ny adgangskode</label>
        <input id="password" type="password" name="password">
        <div class="hint">Lad være tom for at beholde nuværende adgangskode.</div>
      </div>
      <div class="form-row">
        <label for="password_confirmation">Bekræft ny adgangskode</label>
        <input id="password_confirmation" type="password" name="password_confirmation">
      </div>
    </div>

    <button type="submit" class="btn btn-primary">Gem ændringer</button>
  </form>
</div>
@endsection
