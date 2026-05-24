@extends('layouts.app')
@section('content')

@push('styles')
<style>
  .inbox-shell { max-width: 720px; }
  .inbox-empty { background: #fff; border-radius: 12px; padding: 60px 24px; text-align: center; color: var(--muted); box-shadow: 0 1px 2px rgba(0,0,0,0.08); }
  .inbox-empty .icon { font-size: 36px; color: var(--accent); opacity: 0.55; margin-bottom: 10px; }
  .inbox-empty h3 { color: var(--text); margin-bottom: 6px; font-size: 17px; }
</style>
@endpush

<div class="view-header">
  <h1>Indbakke</h1>
  @include('partials.header-actions')
</div>

<div class="inbox-shell">
  <div class="inbox-empty">
    <div class="icon"><i class="fa-regular fa-envelope-open"></i></div>
    <h3>Ingen beskeder endnu</h3>
    <p>Privatbeskeder fra trænere og andre medlemmer dukker op her.</p>
  </div>
</div>

@endsection
