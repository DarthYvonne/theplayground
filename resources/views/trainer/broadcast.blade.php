@extends('layouts.app')
@section('content')

<div class="view-header">
  <h1>
    <a href="{{ route('courses.show', $course) }}" style="color:inherit;"><i class="fa-solid fa-arrow-left" style="font-size:16px;margin-right:8px;"></i></a>
    Skriv til deltagere — {{ $course->title }}
  </h1>
  @include('partials.header-actions')
</div>

<div style="max-width:720px;">
  <form method="POST" action="{{ route('trainer.broadcast.send', $course) }}" class="card card-pad">
    @csrf
    <div class="alert alert-info"><i class="fa-solid fa-circle-info"></i> Sender en rigtig e-mail til alle {{ $course->activeCount() }} aktive deltagere. Svar-til: {{ auth()->user()->email }}</div>
    <div class="form-row">
      <label for="subject">Emne</label>
      <input id="subject" name="subject" type="text" value="{{ old('subject') }}" maxlength="200" required>
    </div>
    <div class="form-row">
      <label for="body">Besked</label>
      <textarea id="body" name="body" rows="10" required>{{ old('body') }}</textarea>
      <div class="hint">Almindelig tekst. Linjeskift bevares.</div>
    </div>
    <button class="btn btn-primary" type="submit"><i class="fa-regular fa-paper-plane"></i> Send til {{ $course->activeCount() }} {{ $course->activeCount() === 1 ? 'deltager' : 'deltagere' }}</button>
  </form>

  @if ($history->count())
    <div class="card" style="overflow:hidden;margin-top:18px;">
      <div style="padding:12px 18px;border-bottom:1px solid #f0f2f5;font-weight:700;">Tidligere e-mails</div>
      @foreach ($history as $h)
        <div style="padding:12px 18px;border-top:1px solid #f0f2f5;">
          <div style="display:flex;justify-content:space-between;gap:8px;align-items:baseline;">
            <div style="font-weight:600;">{{ $h->subject }}</div>
            <div style="color:var(--muted);font-size:12px;">{{ $h->sent_at?->diffForHumans() }} · {{ $h->recipient_count }} {{ $h->recipient_count === 1 ? 'modtager' : 'modtagere' }}</div>
          </div>
          <div style="color:var(--muted);font-size:13px;margin-top:4px;">fra {{ $h->sender->name }}</div>
        </div>
      @endforeach
    </div>
  @endif
</div>
@endsection
