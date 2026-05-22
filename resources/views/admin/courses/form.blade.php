@extends('layouts.app')
@section('content')

<div class="view-header">
  <h1>
    <a href="{{ route('admin.courses.index') }}" style="color:inherit;"><i class="fa-solid fa-arrow-left" style="font-size:16px;margin-right:8px;"></i></a>
    {{ $course->exists ? 'Edit course' : 'New course' }}
  </h1>
  @include('partials.header-actions')
</div>

<div style="max-width: 720px; margin: 0 auto;">
  <form method="POST" action="{{ $course->exists ? route('admin.courses.update', $course) : route('admin.courses.store') }}" enctype="multipart/form-data" class="card card-pad">
    @csrf
    <div class="form-row">
      <label for="title">Title</label>
      <input id="title" type="text" name="title" value="{{ old('title', $course->title) }}" required>
    </div>
    <div class="form-row">
      <label for="description">Description</label>
      <textarea id="description" name="description" rows="6" required>{{ old('description', $course->description) }}</textarea>
    </div>
    <div class="form-row">
      <label for="trainer_id">Trainer</label>
      <select id="trainer_id" name="trainer_id" required>
        @foreach ($trainers as $t)
          <option value="{{ $t->id }}" {{ (string) old('trainer_id', $course->trainer_id) === (string) $t->id ? 'selected' : '' }}>{{ $t->name }} ({{ $t->role }})</option>
        @endforeach
      </select>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;" class="grid-2">
      <div class="form-row">
        <label for="price_cents">Price (cents/month)</label>
        <input id="price_cents" type="number" name="price_cents" min="0" value="{{ old('price_cents', $course->price_cents ?? 0) }}" required>
        <div class="hint">e.g. 49900 = 499 kr/month.</div>
      </div>
      <div class="form-row">
        <label for="max_participants">Max participants</label>
        <input id="max_participants" type="number" name="max_participants" min="1" value="{{ old('max_participants', $course->max_participants ?? 10) }}" required>
      </div>
    </div>
    <div class="form-row">
      <label for="image">Cover image</label>
      <input id="image" type="file" name="image" accept="image/*">
      @if ($course->image_path)
        <div style="margin-top:8px;"><img src="{{ $course->imageUrl() }}" alt="" style="max-height:160px;border-radius:8px;"></div>
      @endif
    </div>
    <div class="form-row">
      <label class="switch">
        <input type="checkbox" name="is_active" value="1" {{ old('is_active', $course->is_active) ? 'checked' : '' }}>
        <span class="knob"></span>
        <span>Active (visible in catalog)</span>
      </label>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;">
      <button class="btn btn-primary" type="submit">{{ $course->exists ? 'Save changes' : 'Create course' }}</button>
      @if ($course->exists)
        <a href="{{ route('courses.show', $course) }}" class="btn btn-secondary"><i class="fa-regular fa-eye"></i> Preview</a>
        <form method="POST" action="{{ route('admin.courses.destroy', $course) }}" onsubmit="return confirm('Delete this course and all related enrollments?');">
          @csrf
          <button class="btn btn-danger" type="submit"><i class="fa-solid fa-trash"></i> Delete</button>
        </form>
      @endif
    </div>
  </form>
</div>

@push('styles')
<style>@media (max-width: 600px) { .grid-2 { grid-template-columns: 1fr !important; } }</style>
@endpush

@endsection
