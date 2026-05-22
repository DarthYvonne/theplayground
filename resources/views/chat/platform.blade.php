@extends('layouts.app')
@section('content')

<div class="view-header">
  <h1>Platform chat</h1>
  @include('partials.header-actions')
</div>

@include('chat._room', [
  'title' => '#all',
  'sub' => 'Everyone at The Playground hangs out here.',
  'icon' => 'fa-solid fa-hashtag',
  'listUrl' => url('/api/chat/platform'),
  'sendUrl' => url('/api/chat/platform'),
])

@endsection
