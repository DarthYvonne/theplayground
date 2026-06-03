@php
  /** @var \App\Models\Course $course */
  $u = auth()->user();
  $canManageCourse = $u && ($u->isOwner() || $course->hasTrainer($u));
  $hasAccess = $canManageCourse || ($u && $u->enrolledIn($course));
  $unreadCount = 0;
  $showMediaTab = false;
  if ($hasAccess) {
    $lastRead = \App\Models\MessageRead::where('user_id', $u->id)->where('course_id', $course->id)->value('last_read_at');
    $q = \App\Models\Message::where('channel_type', 'course')->where('course_id', $course->id)->where('user_id', '!=', $u->id);
    if ($lastRead) $q->where('created_at', '>', $lastRead);
    $unreadCount = $q->count();
    // Trainers/owners always see Medier (so they can add); members only when there's content.
    $showMediaTab = $canManageCourse || \App\Models\CourseMedia::where('course_id', $course->id)->exists();
  }
@endphp

@if ($hasAccess)
@push('styles')
<style>
  .course-tabs { display: flex; gap: 4px; border-bottom: 1px solid var(--border); margin-bottom: 20px; }
  .course-tabs a { display: inline-flex; align-items: center; gap: 8px; padding: 10px 16px; color: var(--muted); font-weight: 600; font-size: 14px; border-bottom: 2px solid transparent; margin-bottom: -1px; text-decoration: none; position: relative; }
  .course-tabs a:hover { color: var(--text); }
  .course-tabs a.active { color: var(--accent); border-bottom-color: var(--accent); }
  .course-tabs i { font-size: 14px; }
  .course-tabs .tab-badge { background: var(--danger); color: #fff; font-size: 10px; font-weight: 700; min-width: 18px; height: 18px; border-radius: 999px; padding: 0 5px; display: inline-flex; align-items: center; justify-content: center; line-height: 1; }
  .course-tabs .tab-dot { width: 8px; height: 8px; background: var(--danger); border-radius: 50%; display: inline-block; }

  @media (max-width: 767px) {
    /* Native-app style: pin to bottom, leave room above. */
    .course-tabs {
      position: fixed; bottom: 0; left: 0; right: 0;
      background: #fff;
      border-top: 1px solid var(--border);
      border-bottom: none;
      margin: 0;
      padding: 0 0 env(safe-area-inset-bottom);
      z-index: 90;
      box-shadow: 0 -2px 8px rgba(0,0,0,0.05);
      justify-content: space-around;
      gap: 0;
    }
    .course-tabs a {
      flex: 1;
      flex-direction: column;
      gap: 0;
      padding: 24px 4px;
      border-bottom: none;
      margin-bottom: 0;
      text-align: center;
      justify-content: center;
    }
    .course-tabs a span:not(.tab-badge):not(.tab-dot) { display: none; }
    .course-tabs a i { font-size: 24px; }
    .course-tabs a.active { color: var(--accent); border-bottom: none; }
    .course-tabs .tab-badge { position: absolute; top: 6px; right: calc(50% - 20px); }
    .course-tabs .tab-dot { position: absolute; top: 8px; right: calc(50% - 14px); }

    /* Keep page content clear of the fixed tab bar. */
    .main { padding-bottom: calc(70px + env(safe-area-inset-bottom)); }
  }
</style>
@endpush

<nav class="course-tabs" aria-label="Hold-faner">
  <a href="{{ route('courses.show', $course) }}" class="{{ request()->routeIs('courses.show') ? 'active' : '' }}" aria-label="Om">
    <i class="fa-solid fa-house"></i><span>Om</span>
  </a>
  <a href="{{ route('chat.course', $course) }}" class="{{ request()->routeIs('chat.course') ? 'active' : '' }}" aria-label="Chat">
    <i class="fa-regular fa-comment"></i><span>Chat</span>
    @if ($unreadCount > 0 && !request()->routeIs('chat.course'))
      <span class="tab-badge">{{ $unreadCount > 99 ? '99+' : $unreadCount }}</span>
    @endif
  </a>
  @if ($showMediaTab)
    <a href="{{ route('courses.media', $course) }}" class="{{ request()->routeIs('courses.media') ? 'active' : '' }}" aria-label="Medier">
      <i class="fa-solid fa-photo-film"></i><span>Medier</span>
    </a>
  @endif
  <a href="{{ route('courses.members', $course) }}" class="{{ request()->routeIs('courses.members') ? 'active' : '' }}" aria-label="Medlemmer">
    <i class="fa-solid fa-users"></i><span>Medlemmer</span>
  </a>
</nav>
@endif
