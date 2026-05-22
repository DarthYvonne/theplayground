@extends('layouts.app')
@section('content')

<div class="view-header">
  <h1>Brugere</h1>
  @include('partials.header-actions')
</div>

<div class="card" style="overflow:hidden;">
  @foreach ($users as $u)
    <div style="display:flex;gap:12px;align-items:center;padding:12px 16px;border-top:1px solid #f0f2f5;">
      @include('partials.avatar', ['u' => $u])
      <div style="flex:1;min-width:0;">
        <div style="font-weight:700;">{{ $u->name }}</div>
        <div style="color:var(--muted);font-size:13px;">{{ $u->email }} @if ($u->phone) · {{ $u->phone }} @endif</div>
      </div>
      <form method="POST" action="{{ route('admin.users.role', $u) }}" style="display:flex;gap:6px;align-items:center;">
        @csrf
        <select name="role" onchange="this.form.submit()" style="width:auto;">
          <option value="user" {{ $u->role === 'user' ? 'selected' : '' }}>Bruger</option>
          <option value="trainer" {{ $u->role === 'trainer' ? 'selected' : '' }}>Træner</option>
          <option value="owner" {{ $u->role === 'owner' ? 'selected' : '' }}>Ejer</option>
        </select>
      </form>
    </div>
  @endforeach
</div>

@endsection
