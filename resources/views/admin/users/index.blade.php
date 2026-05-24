@extends('layouts.app')
@section('content')

<div class="view-header">
  <h1>Indstillinger</h1>
  @include('partials.header-actions')
</div>

@include('admin.settings._subnav')

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
          <option value="assistant" {{ $u->role === 'assistant' ? 'selected' : '' }}>Assistent</option>
          <option value="trainer" {{ $u->role === 'trainer' ? 'selected' : '' }}>Træner</option>
          <option value="owner" {{ $u->role === 'owner' ? 'selected' : '' }}>Ejer</option>
        </select>
      </form>
      @if ($u->id !== auth()->id())
        <form method="POST" action="{{ route('admin.users.destroy', $u) }}"
              onsubmit="return confirm('Slet {{ addslashes($u->name) }}? Dette kan ikke fortrydes.');"
              style="display:inline;">
          @csrf
          <button type="submit" class="btn btn-danger btn-sm" title="Slet bruger" aria-label="Slet bruger">
            <i class="fa-solid fa-trash"></i>
          </button>
        </form>
      @endif
    </div>
  @endforeach
</div>

@endsection
