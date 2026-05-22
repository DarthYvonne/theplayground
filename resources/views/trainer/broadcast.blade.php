@extends('layouts.app')
@section('content')

<div class="view-header">
  <h1>
    <a href="{{ route('courses.show', $course) }}" style="color:inherit;"><i class="fa-solid fa-arrow-left" style="font-size:16px;margin-right:8px;"></i></a>
    Email participants — {{ $course->title }}
  </h1>
  @include('partials.header-actions')
</div>

<div style="max-width:720px;margin:0 auto;">
  <form method="POST" action="{{ route('trainer.broadcast.send', $course) }}" class="card card-pad">
    @csrf
    <div class="alert alert-info"><i class="fa-solid fa-circle-info"></i> Sends a real email to all {{ $course->activeCount() }} active participants. Reply-to: {{ auth()->user()->email }}</div>
    <div class="form-row">
      <label for="subject">Subject</label>
      <input id="subject" name="subject" type="text" value="{{ old('subject') }}" maxlength="200" required>
    </div>
    <div class="form-row">
      <label for="body">Message</label>
      <textarea id="body" name="body" rows="10" required>{{ old('body') }}</textarea>
      <div class="hint">Plain text. Line breaks are preserved.</div>
    </div>
    <button class="btn btn-primary" type="submit"><i class="fa-regular fa-paper-plane"></i> Send to {{ $course->activeCount() }} participant(s)</button>
  </form>

  @if ($history->count())
    <div class="card" style="overflow:hidden;margin-top:18px;">
      <div style="padding:12px 18px;border-bottom:1px solid #f0f2f5;font-weight:700;">Recent broadcasts</div>
      @foreach ($history as $h)
        <div style="padding:12px 18px;border-top:1px solid #f0f2f5;">
          <div style="display:flex;justify-content:space-between;gap:8px;align-items:baseline;">
            <div style="font-weight:600;">{{ $h->subject }}</div>
            <div style="color:var(--muted);font-size:12px;">{{ $h->sent_at?->diffForHumans() }} · {{ $h->recipient_count }} recipient(s)</div>
          </div>
          <div style="color:var(--muted);font-size:13px;margin-top:4px;">by {{ $h->sender->name }}</div>
        </div>
      @endforeach
    </div>
  @endif
</div>
@endsection
