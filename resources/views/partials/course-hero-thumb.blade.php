@php
  /** @var \App\Models\Course $course */
  $heroUrl = $course->heroImageUrl();
  $placeholderIcon = $placeholderIcon ?? 'fa-dumbbell';
  $imgClass = $imgClass ?? 'course-tile-img';
  $phClass = $phClass ?? 'course-tile-img course-tile-img-ph';
  $badgeClass = $badgeClass ?? 'course-tile-video-badge';
  $processing = $course->hasVideo() && in_array($course->video_processing_status, ['pending','processing'], true);
@endphp
@if ($heroUrl)
  <img src="{{ $heroUrl }}" alt="" class="{{ $imgClass }}">
  @if ($course->hasVideo())
    <span class="{{ $badgeClass }} {{ $processing ? 'processing' : '' }}" aria-hidden="true">
      <i class="fa-solid {{ $processing ? 'fa-spinner' : 'fa-play' }}"></i>
    </span>
  @endif
@else
  <div class="{{ $phClass }}"><i class="fa-solid {{ $placeholderIcon }}"></i></div>
@endif
