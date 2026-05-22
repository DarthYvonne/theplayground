@extends('layouts.app')
@section('content')

<div class="view-header">
  <h1>Fælles chat</h1>
  @include('partials.header-actions')
</div>

@include('chat._room', [
  'title' => '#alle',
  'sub' => 'Alle på The Playground mødes her.',
  'icon' => 'fa-solid fa-hashtag',
  'listUrl' => url('/api/chat/platform'),
  'sendUrl' => url('/api/chat/platform'),
])

@endsection
