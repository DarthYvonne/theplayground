@extends('layouts.app')
@section('content')

@push('styles')
<style>
  .course-detail { max-width: 720px; }

  .hero-img { display: block; width: 100%; max-height: 360px; object-fit: cover; }
  .hero-ph { width: 100%; height: 240px; background: linear-gradient(135deg, var(--accent-soft), #f5f7fb); display: flex; align-items: center; justify-content: center; font-size: 80px; color: var(--accent); }

  .course-body { padding: 24px; }
  .course-title { font-size: 28px; font-weight: 800; line-height: 1.2; }

  .info-ribbon { display: flex; flex-wrap: wrap; gap: 6px 18px; margin-top: 14px; padding: 12px 0; border-top: 1px solid #f0f2f5; border-bottom: 1px solid #f0f2f5; color: var(--muted); font-size: 14px; }
  .info-ribbon .item { display: inline-flex; align-items: center; gap: 6px; }
  .info-ribbon .item i { font-size: 12px; opacity: 0.85; }
  .info-ribbon .item strong { color: var(--text); font-weight: 600; }
  .info-ribbon .full { color: #b91c1c; font-weight: 700; }
  .info-ribbon .draft { color: #b91c1c; font-weight: 700; }

  .trainer-line { display: flex; align-items: center; gap: 8px; margin-top: 14px; color: var(--muted); font-size: 13px; }
  .trainer-line .name { color: var(--text); font-weight: 600; }

  .desc { margin-top: 18px; line-height: 1.6; white-space: pre-wrap; color: #3a3d42; }

  .card-footer { background: #fafbfc; border-top: 1px solid #f0f2f5; padding: 16px 24px; display: flex; gap: 14px; align-items: center; flex-wrap: wrap; }
  .card-footer .footer-left { display: flex; gap: 14px; align-items: center; flex-wrap: wrap; flex: 1; min-width: 0; }
  .card-footer .afmeld { background: none; border: none; padding: 0; color: var(--muted); font-size: 13px; cursor: pointer; font-family: inherit; text-decoration: underline; text-underline-offset: 3px; }
  .card-footer .afmeld:hover { color: var(--danger); }
  .card-footer .enrolled-note { display: inline-flex; align-items: center; gap: 6px; color: #166534; font-weight: 700; font-size: 14px; }

  .card-footer .owner-actions { display: inline-flex; gap: 4px; margin-left: auto; }
  .cf-iconbtn { width: 36px; height: 36px; display: inline-flex; align-items: center; justify-content: center; border: 1px solid var(--border); background: #fff; color: var(--muted); border-radius: 8px; cursor: pointer; font-size: 14px; padding: 0; }
  .cf-iconbtn:hover { background: var(--hover); color: var(--text); border-color: var(--muted); }
  .cf-iconbtn.danger:hover { background: #fef2f2; color: var(--danger); border-color: var(--danger); }
  .card-footer .owner-actions form { display: inline-flex; margin: 0; }

  @media (max-width: 767px) {
    .course-body { padding: 18px; }
    .course-title { font-size: 22px; }
    .card-footer { padding: 14px 18px; }
    .card-footer .footer-left .btn { width: 100%; justify-content: center; }
  }
</style>
@endpush

<div class="view-header">
  <h1>
    <a href="{{ url('/') }}" style="color:inherit;"><i class="fa-solid fa-arrow-left" style="font-size:16px;margin-right:8px;"></i></a>
    {{ $course->title }}
  </h1>
  @include('partials.header-actions')
</div>

@include('partials.course-tabs')

<div class="course-detail">
  <div class="card">
    @if ($course->image_path)
      <img src="{{ $course->imageUrl() }}" alt="" class="hero-img">
    @else
      <div class="hero-ph"><i class="fa-solid fa-dumbbell"></i></div>
    @endif

    <div class="course-body">
      <h1 class="course-title">{{ $course->title }}</h1>

      <div class="info-ribbon">
        <span class="item"><strong>{{ $course->price() }}</strong></span>
        @if ($course->free_enrollment)
          <span class="item" style="color:#166534;font-weight:700;"><i class="fa-solid fa-gift"></i> Gratis tilmelding</span>
        @endif
        @if ($course->scheduleLabel())
          <span class="item"><i class="fa-regular fa-clock"></i>{{ $course->scheduleLabel() }}</span>
        @endif
        <span class="item">
          <i class="fa-regular fa-user"></i>
          <strong>{{ $course->activeCount() }}/{{ $course->max_participants }}</strong> tilmeldt
          @if ($course->isFull())<span class="full">· Fuldt</span>@endif
        </span>
        @if (auth()->user()?->isOwner() && !$course->is_active)
          <span class="item draft">Kladde</span>
        @endif
      </div>

      @if ($course->trainers->isNotEmpty())
        <div class="trainer-line">
          @include('partials.avatar', ['u' => $course->primaryTrainer(), 'size' => 'sm'])
          <span>Med <span class="name">{{ $course->trainerNames() }}</span></span>
        </div>
      @endif

      @if (trim((string) $course->description) !== '')
        <div class="desc">{{ $course->description }}</div>
      @endif
    </div>

    <div class="card-footer">
      <div class="footer-left">
        @auth
          @if ($isEnrolled)
            @if ($enrollment && $enrollment->cancel_at_period_end)
              <span class="enrolled-note" style="color:#92400e;">
                <i class="fa-solid fa-clock"></i>
                Afmeldt — adgang frem til {{ optional($enrollment->current_period_end)->format('d.m.Y') ?? '–' }}
              </span>
            @else
              <button class="afmeld" type="button" onclick="document.getElementById('afmeldModal').classList.add('open')">Afmeld</button>
              <span class="enrolled-note"><i class="fa-solid fa-circle-check"></i> Du er tilmeldt</span>
            @endif
          @elseif ($course->isFull())
            <button class="btn btn-secondary" disabled>Holdet er fuldt</button>
          @else
            <form method="POST" action="{{ route('enroll', $course) }}">
              @csrf
              <button class="btn btn-primary" type="submit">Tilmeld dig</button>
            </form>
          @endif
        @else
          <a href="{{ route('login') }}" class="btn btn-primary">Log ind for at tilmelde dig</a>
        @endauth
      </div>

      @auth
        @if (auth()->user()->isOwner())
          <div class="owner-actions">
            <a href="{{ route('admin.courses.edit', $course) }}" class="cf-iconbtn" title="Rediger hold" aria-label="Rediger hold"><i class="fa-solid fa-pen"></i></a>
            <form method="POST" action="{{ route('admin.courses.destroy', $course) }}" onsubmit="return confirm('Slet dette hold? Det kan ikke fortrydes.');">
              @csrf
              <button class="cf-iconbtn danger" type="submit" title="Slet hold" aria-label="Slet hold"><i class="fa-solid fa-trash"></i></button>
            </form>
          </div>
        @endif
      @endauth
    </div>
  </div>
</div>

@auth
  @if ($isEnrolled && $enrollment && !$enrollment->cancel_at_period_end)
    <div class="afmeld-modal" id="afmeldModal" role="dialog" aria-modal="true" aria-labelledby="afmeldModalTitle">
      <div class="afmeld-modal-backdrop" onclick="document.getElementById('afmeldModal').classList.remove('open')"></div>
      <div class="afmeld-modal-card">
        <h2 id="afmeldModalTitle">Afmeld {{ $course->title }}?</h2>
        <p>
          @if ($enrollment->current_period_end)
            Du beholder din adgang frem til <strong>{{ $enrollment->current_period_end->format('d.m.Y') }}</strong>. Vi opkræver ikke flere betalinger.
          @elseif ($enrollment->stripe_subscription_id)
            Du beholder adgang resten af din nuværende betalingsperiode, og vi opkræver dig ikke igen.
          @else
            Din tilmelding bliver annulleret med det samme.
          @endif
        </p>
        <form method="POST" action="{{ route('enroll.cancel', $course) }}">
          @csrf
          <label for="afmeldConfirm">Skriv <strong>Afmeld</strong> for at bekræfte</label>
          <input id="afmeldConfirm" name="confirm" type="text" autocomplete="off" autocapitalize="words" required>
          <div class="afmeld-modal-actions">
            <button type="button" class="btn btn-ghost" onclick="document.getElementById('afmeldModal').classList.remove('open')">Annuller</button>
            <button type="submit" class="btn btn-danger" id="afmeldConfirmBtn" disabled>Afmeld endeligt</button>
          </div>
        </form>
      </div>
    </div>
    @push('styles')
    <style>
      .afmeld-modal { display: none; position: fixed; inset: 0; z-index: 1050; align-items: center; justify-content: center; padding: 16px; }
      .afmeld-modal.open { display: flex; }
      .afmeld-modal-backdrop { position: absolute; inset: 0; background: rgba(0,0,0,0.5); }
      .afmeld-modal-card { position: relative; background: #fff; border-radius: 12px; padding: 22px 24px; max-width: 460px; width: 100%; box-shadow: 0 20px 60px rgba(0,0,0,0.25); }
      .afmeld-modal-card h2 { font-size: 18px; font-weight: 700; margin-bottom: 8px; }
      .afmeld-modal-card p { color: var(--muted); line-height: 1.5; margin-bottom: 16px; }
      .afmeld-modal-card label { font-size: 13px; font-weight: 600; margin-bottom: 6px; display: block; }
      .afmeld-modal-card input { width: 100%; }
      .afmeld-modal-actions { display: flex; gap: 8px; justify-content: flex-end; margin-top: 14px; }
    </style>
    @endpush
    @push('scripts')
    <script>
      (function () {
        var input = document.getElementById('afmeldConfirm');
        var btn = document.getElementById('afmeldConfirmBtn');
        if (!input || !btn) return;
        input.addEventListener('input', function () {
          btn.disabled = input.value.trim().toLowerCase() !== 'afmeld';
        });
        var modal = document.getElementById('afmeldModal');
        document.addEventListener('keydown', function (e) {
          if (e.key === 'Escape' && modal.classList.contains('open')) modal.classList.remove('open');
        });
      })();
    </script>
    @endpush
  @endif
@endauth

@endsection
