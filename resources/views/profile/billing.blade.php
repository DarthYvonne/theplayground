@extends('layouts.app')
@section('content')

@push('styles')
<style>
  .billing-shell { }
  .billing-card { padding: 20px 22px; }
  .billing-card h2 { font-size: 16px; font-weight: 700; margin-bottom: 4px; }
  .billing-card .sub { color: var(--muted); font-size: 13px; margin-bottom: 14px; }

  .pm-empty { color: var(--muted); font-size: 14px; padding: 14px; border: 1px dashed var(--border); border-radius: 10px; text-align: center; }

  .sub-list { display: flex; flex-direction: column; gap: 10px; margin-top: 4px; }
  .sub-row { display: flex; gap: 12px; align-items: center; padding: 12px 14px; border: 1px solid #f0f2f5; border-radius: 10px; }
  .sub-row img, .sub-row .thumb-ph { width: 48px; height: 48px; border-radius: 8px; object-fit: cover; flex-shrink: 0; background: var(--accent-soft); color: var(--accent); display: flex; align-items: center; justify-content: center; font-size: 18px; }
  .sub-row .info { flex: 1; min-width: 0; }
  .sub-row .t { font-weight: 700; }
  .sub-row .price { color: var(--muted); font-size: 13px; }
  .sub-row .method { font-size: 12px; color: var(--muted); margin-top: 2px; }
  .sub-row .status { font-size: 12px; font-weight: 700; padding: 3px 8px; border-radius: 999px; }
  .sub-row .status.active { background: #dcfce7; color: #166534; }
  .sub-row .status.pending { background: #fef3c7; color: #92400e; }
  .sub-row .status.past_due { background: #fee2e2; color: #991b1b; }
</style>
@endpush

<div class="view-header">
  <h1>Min profil</h1>
  @include('partials.header-actions')
</div>

@include('profile._subnav')

<div class="billing-shell">
  <div class="card billing-card">
    <h2>Sådan betaler du</h2>
    <div class="sub" style="margin-bottom:0;">
      Medlemskaber betales løbende med <strong>MobilePay</strong>: du godkender aftalen i MobilePay-appen,
      og herefter trækkes beløbet automatisk hver måned. Du kan til enhver tid afmelde et hold — så stopper
      opkrævningen, og du beholder adgangen perioden ud. Kortbetaling kan bruges som alternativ på enkelte hold.
    </div>
  </div>

  <div class="card billing-card">
    <h2>Aktive medlemskaber</h2>
    <div class="sub">Hold du betaler for løbende.</div>

    @if ($enrollments->isEmpty())
      <div class="pm-empty">Du har ingen aktive medlemskaber.</div>
    @else
      <div class="sub-list">
        @foreach ($enrollments as $e)
          <div class="sub-row">
            @if ($e->course->heroImageUrl())
              <img src="{{ $e->course->heroImageUrl() }}" alt="">
            @else
              <div class="thumb-ph"><i class="fa-solid fa-dumbbell"></i></div>
            @endif
            <div class="info">
              <a href="{{ route('courses.show', $e->course) }}" class="t" style="color:inherit;">{{ $e->course->title }}</a>
              <div class="price">
                {{ $e->course->price() }} · tilmeldt {{ $e->enrolled_at?->format('d.m.Y') ?? '—' }}
                @if ($e->cancel_at_period_end && $e->current_period_end)
                  · Slutter {{ $e->current_period_end->format('d.m.Y') }}
                @elseif ($e->current_period_end && $e->status === 'active')
                  · Fornyes {{ $e->current_period_end->format('d.m.Y') }}
                @endif
              </div>
              <div class="method">
                @if ($e->provider === 'mobilepay')
                  <i class="fa-solid fa-mobile-screen-button"></i> MobilePay
                @elseif ($e->provider === 'stripe')
                  <i class="fa-regular fa-credit-card"></i> Kort
                @endif
              </div>
            </div>
            <div>
              @if ($e->cancel_at_period_end)
                <span class="status pending">Afmeldt</span>
              @elseif ($e->status === 'past_due')
                <span class="status past_due">Betaling fejlede</span>
              @elseif ($e->status === 'active')
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
