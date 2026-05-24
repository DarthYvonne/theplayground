@extends('layouts.app')
@section('content')

<div class="view-header">
  <h1>
    <a href="{{ route('admin.courses.index') }}" style="color:inherit;"><i class="fa-solid fa-arrow-left" style="font-size:16px;margin-right:8px;"></i></a>
    {{ $course->exists ? 'Rediger hold' : 'Nyt hold' }}
  </h1>
  @include('partials.header-actions')
</div>

<div style="max-width: 720px;">
  <form method="POST" action="{{ $course->exists ? route('admin.courses.update', $course) : route('admin.courses.store') }}" enctype="multipart/form-data" class="card card-pad">
    @csrf
    <div class="form-row">
      <label for="title">Titel</label>
      <input id="title" type="text" name="title" value="{{ old('title', $course->title) }}" required>
    </div>
    <div class="form-row">
      <label for="description">Beskrivelse</label>
      <textarea id="description" name="description" rows="6" required>{{ old('description', $course->description) }}</textarea>
    </div>
    <div class="form-row">
      <label for="trainer_id">Træner</label>
      <select id="trainer_id" name="trainer_id" required>
        @foreach ($trainers as $t)
          <option value="{{ $t->id }}" {{ (string) old('trainer_id', $course->trainer_id) === (string) $t->id ? 'selected' : '' }}>{{ $t->name }} ({{ ['owner' => 'ejer', 'trainer' => 'træner'][$t->role] ?? $t->role }})</option>
        @endforeach
      </select>
    </div>
    @php
      $priceKr = ($course->price_cents ?? 0) / 100;
      $priceKrDisplay = $priceKr == (int) $priceKr ? (string) (int) $priceKr : rtrim(rtrim(number_format($priceKr, 2, '.', ''), '0'), '.');
    @endphp
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;" class="grid-2">
      <div class="form-row">
        <label for="price_kr">Pris (kr/måned)</label>
        <input id="price_kr" type="number" name="price_kr" min="0" step="0.01" value="{{ old('price_kr', $priceKrDisplay) }}" required>
        <div class="hint">Hele kroner pr. måned. Brug 0 hvis holdet er gratis.</div>
      </div>
      <div class="form-row">
        <label for="max_participants">Maks. deltagere</label>
        <input id="max_participants" type="number" name="max_participants" min="1" value="{{ old('max_participants', $course->max_participants ?? 10) }}" required>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;" class="grid-2">
      <div class="form-row">
        <label for="start_time">Fra</label>
        <input id="start_time" type="time" name="start_time" value="{{ old('start_time', $course->start_time ? substr((string) $course->start_time, 0, 5) : '') }}">
      </div>
      <div class="form-row">
        <label for="end_time">Til</label>
        <input id="end_time" type="time" name="end_time" value="{{ old('end_time', $course->end_time ? substr((string) $course->end_time, 0, 5) : '') }}">
      </div>
    </div>

    <div class="form-row">
      <label>Ugedag(e)</label>
      @php $selectedDays = is_array(old('weekdays')) ? old('weekdays') : $course->weekdaysList(); @endphp
      <div class="weekday-row">
        @foreach (\App\Models\Course::WEEKDAYS as $code => $name)
          <label class="weekday-chip">
            <input type="checkbox" name="weekdays[]" value="{{ $code }}" {{ in_array($code, $selectedDays, true) ? 'checked' : '' }}>
            <span>{{ $name }}</span>
          </label>
        @endforeach
      </div>
    </div>
    <div class="form-row">
      <label for="image">Forsidebillede</label>
      <input id="image" type="file" name="image" accept="image/*">
      @if ($course->image_path)
        <div style="margin-top:8px;"><img src="{{ $course->imageUrl() }}" alt="" style="max-height:160px;border-radius:8px;"></div>
      @endif
    </div>
    <div class="form-row">
      <label class="switch">
        <input type="checkbox" name="is_active" value="1" {{ old('is_active', $course->is_active) ? 'checked' : '' }}>
        <span class="knob"></span>
        <span>Aktiv (vises på forsiden)</span>
      </label>
    </div>
    <div class="form-row">
      <label class="switch">
        <input type="checkbox" name="free_enrollment" value="1" {{ old('free_enrollment', $course->free_enrollment) ? 'checked' : '' }}>
        <span class="knob"></span>
        <span>Gratis tilmelding (spring Stripe over &mdash; mest til test)</span>
      </label>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;">
      <button class="btn btn-primary" type="submit">{{ $course->exists ? 'Gem ændringer' : 'Opret hold' }}</button>
      @if ($course->exists)
        <a href="{{ route('courses.show', $course) }}" class="btn btn-secondary"><i class="fa-regular fa-eye"></i> Vis</a>
        <form method="POST" action="{{ route('admin.courses.destroy', $course) }}" onsubmit="return confirm('Slet holdet og alle relaterede tilmeldinger?');">
          @csrf
          <button class="btn btn-danger" type="submit"><i class="fa-solid fa-trash"></i> Slet</button>
        </form>
      @endif
    </div>
  </form>
</div>

@push('styles')
<style>
  @media (max-width: 600px) { .grid-2 { grid-template-columns: 1fr !important; } }
  .weekday-row { display: flex; flex-wrap: wrap; gap: 6px; }
  .weekday-chip { display: inline-flex; align-items: center; gap: 0; cursor: pointer; user-select: none; font-weight: 500; }
  .weekday-chip input { position: absolute; opacity: 0; pointer-events: none; }
  .weekday-chip span { padding: 8px 14px; border-radius: 999px; border: 1px solid var(--border); background: #fff; font-size: 13px; transition: background 0.1s, border-color 0.1s, color 0.1s; }
  .weekday-chip:hover span { background: var(--hover); }
  .weekday-chip input:checked + span { background: var(--accent); border-color: var(--accent); color: #fff; }
</style>
@endpush

@endsection
