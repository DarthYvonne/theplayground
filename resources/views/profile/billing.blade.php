@extends('layouts.app')
@section('content')

@push('styles')
<style>
  .billing-shell { }
  .billing-card { padding: 20px 22px; }
  .billing-card h2 { font-size: 16px; font-weight: 700; margin-bottom: 4px; }
  .billing-card .sub { color: var(--muted); font-size: 13px; margin-bottom: 14px; }

  .pm-row { display: flex; align-items: center; gap: 14px; padding: 14px; border: 1px solid #f0f2f5; border-radius: 10px; }
  .pm-row .icon { width: 44px; height: 44px; border-radius: 10px; background: var(--accent-soft); color: var(--accent); display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }
  .pm-row .label { font-weight: 600; }
  .pm-row .meta { color: var(--muted); font-size: 13px; margin-top: 2px; }
  .pm-empty { color: var(--muted); font-size: 14px; padding: 14px; border: 1px dashed var(--border); border-radius: 10px; text-align: center; }

  .sub-list { display: flex; flex-direction: column; gap: 10px; margin-top: 4px; }
  .sub-row { display: flex; gap: 12px; align-items: center; padding: 12px 14px; border: 1px solid #f0f2f5; border-radius: 10px; }
  .sub-row img, .sub-row .thumb-ph { width: 48px; height: 48px; border-radius: 8px; object-fit: cover; flex-shrink: 0; background: var(--accent-soft); color: var(--accent); display: flex; align-items: center; justify-content: center; font-size: 18px; }
  .sub-row .info { flex: 1; min-width: 0; }
  .sub-row .t { font-weight: 700; }
  .sub-row .price { color: var(--muted); font-size: 13px; }
  .sub-row .status { font-size: 12px; font-weight: 700; padding: 3px 8px; border-radius: 999px; }
  .sub-row .status.active { background: #dcfce7; color: #166534; }
  .sub-row .status.pending { background: #fef3c7; color: #92400e; }
</style>
@endpush

<div class="view-header">
  <h1>Min profil</h1>
  @include('partials.header-actions')
</div>

@include('profile._subnav')

<div class="billing-shell">
  <div class="card billing-card">
    <h2>Betalingsmetode</h2>
    <div class="sub">Bruges til månedlige abonnementer på hold.</div>

    @if ($user->pm_last_four)
      <div class="pm-row">
        <div class="icon"><i class="fa-regular fa-credit-card"></i></div>
        <div style="flex:1;min-width:0;">
          <div class="label">{{ ucfirst($user->pm_type ?? 'Kort') }} •••• {{ $user->pm_last_four }}</div>
          <div class="meta">Gemt på Stripe. Skift kort via portalen nedenfor.</div>
        </div>
      </div>
    @else
      <div class="pm-empty">Intet kort gemt endnu. Når du tilmelder dig dit første hold, gemmes kortet automatisk her.</div>
    @endif

    @if ($stripeConfigured && $user->stripe_id)
      <form method="POST" action="{{ route('profile.billing.portal') }}" style="margin-top:14px;">
        @csrf
        <button type="submit" class="btn btn-primary">
          <i class="fa-solid fa-arrow-up-right-from-square"></i> Åbn Stripe-portalen
        </button>
        <div class="sub" style="margin-top:8px;margin-bottom:0;">Opdater kort, se kvitteringer og opsig abonnementer i Stripes sikre portal.</div>
      </form>
    @endif
  </div>

  <div class="card billing-card">
    <h2>Aktive abonnementer</h2>
    <div class="sub">Hold du betaler for hver måned.</div>

    @if ($enrollments->isEmpty())
      <div class="pm-empty">Du har ingen aktive abonnementer.</div>
    @else
      <div class="sub-list">
        @foreach ($enrollments as $e)
          <div class="sub-row">
            @if ($e->course->image_path)
              <img src="{{ $e->course->imageUrl() }}" alt="">
            @else
              <div class="thumb-ph"><i class="fa-solid fa-dumbbell"></i></div>
            @endif
            <div class="info">
              <a href="{{ route('courses.show', $e->course) }}" class="t" style="color:inherit;">{{ $e->course->title }}</a>
              <div class="price">{{ $e->course->price() }} · tilmeldt {{ $e->enrolled_at?->format('d.m.Y') ?? '—' }}</div>
            </div>
            <div>
              @if ($e->status === 'active')
                <span class="status active">Aktiv</span>
              @else
                <span class="status pending">Afventer</span>
              @endif
            </div>
          </div>
        @endforeach
      </div>
    @endif
  </div>
</div>

@endsection
