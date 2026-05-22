@extends('layouts.app')
@section('content')

@push('styles')
<style>
  .feed-main { max-width: 620px; margin: 0 auto; }
  .course-card { background: #fff; border-radius: 12px; box-shadow: 0 1px 2px rgba(0,0,0,0.1); margin-bottom: 16px; overflow: hidden; }
  .pc-head { display: flex; gap: 10px; align-items: center; padding: 12px 16px 0; }
  .pc-meta { font-weight: 600; line-height: 1.2; }
  .pc-meta small { display: block; color: var(--muted); font-weight: 400; font-size: 12px; margin-top: 2px; }
  .pc-meta a { color: inherit; }
  .pc-title { padding: 10px 16px 6px; font-size: 20px; font-weight: 700; line-height: 1.25; }
  .pc-body { padding: 0 16px 12px; line-height: 1.45; color: #4b4f56; white-space: pre-wrap; }
  .pc-img { display: block; width: 100%; max-height: 480px; object-fit: cover; }
  .pc-img-placeholder { display: block; width: 100%; height: 200px; background: linear-gradient(135deg, var(--accent-soft), #f5f7fb); display: flex; align-items: center; justify-content: center; color: var(--accent); font-size: 60px; }
  .pc-stats { display: flex; justify-content: space-between; padding: 10px 16px; font-size: 13px; color: var(--muted); border-top: 1px solid #f0f2f5; gap: 12px; flex-wrap: wrap; }
  .pc-stats .price { color: var(--text); font-weight: 700; font-size: 15px; }
  .pc-actions { display: flex; padding: 4px 8px; border-top: 1px solid #f0f2f5; }
  .pc-actions a, .pc-actions button { flex: 1; text-align: center; padding: 10px; border-radius: 6px; color: var(--muted); font-weight: 600; font-size: 14px; background: none; border: none; cursor: pointer; font-family: inherit; }
  .pc-actions a:hover, .pc-actions button:hover { background: var(--hover); }
  .pc-actions .primary { color: var(--accent); }
  .pc-actions i { margin-right: 6px; }
  .empty-feed { background: #fff; border-radius: 12px; padding: 60px 20px; text-align: center; color: var(--muted); }
  .hero { background: linear-gradient(135deg, var(--accent) 0%, #6cabff 100%); color: #fff; padding: 28px 20px; border-radius: 14px; margin-bottom: 18px; }
  .hero h2 { font-size: 22px; font-weight: 800; margin-bottom: 6px; line-height: 1.2; }
  .hero p { font-size: 14px; opacity: 0.95; margin-bottom: 12px; }
  .hero .btn { background: #fff; color: var(--accent); }
  .hero .btn:hover { background: #f0f4fc; }
  @media (max-width: 767px) {
    .feed-main { max-width: 100%; }
    .pc-title { font-size: 18px; }
  }
</style>
@endpush

<div class="view-header">
  <h1>Courses</h1>
  @include('partials.header-actions')
</div>

<div class="feed-main">
  @guest
  <div class="hero">
    <h2>Find your next training course</h2>
    <p>Browse what's running at The Playground. Sign up to enroll and chat with trainers and other members.</p>
    <a href="{{ route('register') }}" class="btn">Get started</a>
    <a href="{{ route('login') }}" class="btn" style="background:transparent;color:#fff;border:1px solid rgba(255,255,255,0.6);margin-left:6px;">Log in</a>
  </div>
  @endguest

  @if ($courses->isEmpty())
    <div class="empty-feed">
      <h3 style="color:var(--text);margin-bottom:6px;">No courses just yet</h3>
      <p>Check back soon.</p>
    </div>
  @else
    @foreach ($courses as $course)
      @php $full = $course->active_enrollments_count >= $course->max_participants; @endphp
      <article class="course-card">
        <div class="pc-head">
          @include('partials.avatar', ['u' => $course->trainer])
          <div class="pc-meta">
            <a href="{{ route('courses.show', $course) }}">{{ $course->trainer->name }}</a>
            <small>Trainer · {{ $course->created_at->diffForHumans() }}</small>
          </div>
          <div style="margin-left:auto;display:flex;gap:6px;">
            @if ($full) <span class="tag outline-danger"><i class="fa-solid fa-user-slash"></i> Full</span>
            @else <span class="tag success">{{ $course->slotsLeft() }} spot{{ $course->slotsLeft() === 1 ? '' : 's' }} left</span>
            @endif
          </div>
        </div>
        <a href="{{ route('courses.show', $course) }}">
          <h2 class="pc-title">{{ $course->title }}</h2>
        </a>
        <div class="pc-body">{{ \Illuminate\Support\Str::limit($course->description, 220) }}</div>
        @if ($course->image_path)
          <a href="{{ route('courses.show', $course) }}"><img class="pc-img" src="{{ $course->imageUrl() }}" alt=""></a>
        @else
          <a href="{{ route('courses.show', $course) }}"><div class="pc-img-placeholder"><i class="fa-solid fa-dumbbell"></i></div></a>
        @endif
        <div class="pc-stats">
          <span class="price">{{ $course->price() }}</span>
          <span><i class="fa-regular fa-user"></i> {{ $course->active_enrollments_count }}/{{ $course->max_participants }} enrolled</span>
        </div>
        <div class="pc-actions">
          <a href="{{ route('courses.show', $course) }}"><i class="fa-regular fa-eye"></i> Details</a>
          @auth
            @if (auth()->user()->enrolledIn($course))
              <a href="{{ route('chat.course', $course) }}" class="primary"><i class="fa-regular fa-comments"></i> Chat</a>
            @elseif ($full)
              <button disabled><i class="fa-solid fa-lock"></i> Full</button>
            @else
              <form method="POST" action="{{ route('enroll', $course) }}" style="flex:1;display:flex;">
                @csrf
                <button type="submit" class="primary" style="flex:1;"><i class="fa-solid fa-bolt"></i> Enroll</button>
              </form>
            @endif
          @else
            <a href="{{ route('login') }}" class="primary"><i class="fa-solid fa-bolt"></i> Enroll</a>
          @endauth
        </div>
      </article>
    @endforeach
  @endif
</div>

@endsection
