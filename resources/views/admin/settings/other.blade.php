@extends('layouts.app')
@section('content')

@push('styles')
<style>
  .settings-card { background: #fff; border-radius: 12px; box-shadow: 0 1px 2px rgba(0,0,0,0.08); padding: 18px 20px; max-width: 520px; }
  .settings-card h2 { font-size: 16px; font-weight: 700; margin-bottom: 4px; }
  .settings-card .hint { color: var(--muted); font-size: 13px; margin-bottom: 14px; }
  .settings-card label { display: block; font-size: 12px; text-transform: uppercase; letter-spacing: 0.4px; color: var(--muted); font-weight: 700; margin-bottom: 6px; }
  .settings-card select { width: 100%; padding: 10px 12px; font-size: 14px; border: 1px solid var(--border); border-radius: 8px; background: #fff; color: var(--text); }
  .settings-card select:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px rgba(24,119,242,0.15); }
</style>
@endpush

<div class="view-header">
  <h1>Indstillinger</h1>
  @include('partials.header-actions')
</div>

@include('admin.settings._subnav')

@php $previewRole = session('preview_role'); @endphp

<div class="settings-card">
  <h2>Vis som</h2>
  <div class="hint">Forhåndsvis siden som en anden rolle. Påvirker kun din egen session.</div>
  <form method="POST" action="{{ route('preview.role') }}">
    @csrf
    <label for="previewRoleSelect">Rolle</label>
    <select id="previewRoleSelect" name="role" onchange="this.form.submit()">
      <option value="owner" {{ !$previewRole ? 'selected' : '' }}>Ejer (mig)</option>
      <option value="trainer" {{ $previewRole === 'trainer' ? 'selected' : '' }}>Træner</option>
      <option value="assistant" {{ $previewRole === 'assistant' ? 'selected' : '' }}>Assistent</option>
      <option value="user" {{ $previewRole === 'user' ? 'selected' : '' }}>Bruger</option>
    </select>
  </form>
</div>

@endsection
