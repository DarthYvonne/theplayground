@extends('layouts.app')
@section('content')

<div class="view-header">
  <h1>Indstillinger</h1>
  @include('partials.header-actions')
</div>

@include('admin.settings._subnav')

<div style="margin-bottom:14px;">
  <a href="{{ route('admin.users.index') }}" style="color:var(--muted);text-decoration:none;font-size:13px;">
    <i class="fa-solid fa-arrow-left"></i> Tilbage til brugere
  </a>
</div>

@if (session('status'))
  <div class="alert alert-success" style="margin-bottom:14px;">{{ session('status') }}</div>
@endif

@if ($errors->any())
  <div class="alert alert-danger" style="margin-bottom:14px;">
    @foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach
  </div>
@endif

<form method="POST" action="{{ route('admin.users.update', $user) }}" enctype="multipart/form-data" class="card card-pad">
  @csrf
  <div style="display:flex;gap:16px;align-items:center;margin-bottom:18px;">
    @include('partials.avatar', ['u' => $user, 'size' => 'xl'])
    <div style="flex:1;min-width:0;">
      <div style="font-weight:700;font-size:16px;">{{ $user->name }}</div>
      <div style="color:var(--muted);font-size:13px;">{{ $user->email }}</div>
    </div>
    <div style="display:flex;gap:8px;align-items:center;">
      <form method="POST" action="{{ route('admin.users.role', $user) }}" style="display:flex;align-items:center;">
        @csrf
        <select name="role" onchange="this.form.submit()" style="width:auto;">
          <option value="user" {{ $user->role === 'user' ? 'selected' : '' }}>Bruger</option>
          <option value="assistant" {{ $user->role === 'assistant' ? 'selected' : '' }}>Assistent</option>
          <option value="trainer" {{ $user->role === 'trainer' ? 'selected' : '' }}>Træner</option>
          <option value="owner" {{ $user->role === 'owner' ? 'selected' : '' }}>Ejer</option>
        </select>
      </form>
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
    <label for="about">Om</label>
    <textarea id="about" name="about" rows="4">{{ old('about', $user->about) }}</textarea>
  </div>

  <div style="border-top:1px solid #f0f2f5;padding-top:16px;margin-top:8px;">
    <div style="font-weight:700;margin-bottom:10px;">Nulstil adgangskode</div>
    <div class="form-row">
      <label for="password">Ny adgangskode</label>
      <input id="password" type="password" name="password" autocomplete="new-password">
      <div class="hint">Lad være tom for at beholde nuværende adgangskode.</div>
    </div>
    <div class="form-row">
      <label for="password_confirmation">Bekræft ny adgangskode</label>
      <input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password">
    </div>
  </div>

  <div style="display:flex;gap:8px;align-items:center;justify-content:space-between;">
    <button type="submit" class="btn btn-primary">Gem ændringer</button>

    @if ($user->id !== auth()->id())
      <button type="button" class="btn btn-danger" onclick="if (confirm('Slet {{ addslashes($user->name) }}? Dette kan ikke fortrydes.')) document.getElementById('delete-user-form').submit();">
        <i class="fa-solid fa-trash"></i> Slet bruger
      </button>
    @endif
  </div>
</form>

@if ($user->id !== auth()->id())
  <form id="delete-user-form" method="POST" action="{{ route('admin.users.destroy', $user) }}" style="display:none;">
    @csrf
  </form>
@endif

@endsection
