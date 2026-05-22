@php /** @var \App\Models\User $u */ $size = $size ?? ''; @endphp
@if ($u->picture_path)
  <div class="av {{ $size }}"><img src="{{ $u->pictureUrl() }}" alt=""></div>
@else
  <div class="av {{ $size }}">{{ $u->initials() }}</div>
@endif
