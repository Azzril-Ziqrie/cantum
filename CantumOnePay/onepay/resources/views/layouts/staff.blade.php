@php
    $user = auth()->user();
    $nav = array_filter(config('onepay_nav'), fn ($entry) => \Illuminate\Support\Facades\Route::has($entry[0]) && $user->role->{$entry[3]}());
@endphp
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex">
<meta name="theme-color" content="#f6f8fc">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $title ?? 'Dashboard' }} · {{ $brand->name }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:ital,wght@0,400;0,500;1,400&display=swap">
@include('partials.head-assets')
</head>
<body class="shell-body">
<div class="shell">
  <aside class="side" aria-label="Menu utama">
    <a class="brand side-brand" href="{{ route('staff.dashboard') }}">{{ $brand->name }}</a>
    <nav class="side-nav">
      @foreach ($nav as [$route, $label, $icon])
        <a class="nav" href="{{ route($route) }}" @if (request()->routeIs($route)) aria-current="page" @endif>
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="{{ $icon }}"/></svg><span>{{ $label }}</span>
        </a>
      @endforeach
    </nav>
    <div class="side-foot">
      <p class="label">{{ $user->name }} · {{ $user->role->value }}</p>
      <form method="post" action="{{ route('staff.logout') }}">@csrf<button class="btn btn--light btn--small btn--block" type="submit">Keluar</button></form>
    </div>
  </aside>
  <div class="main">
    <header class="topbar">
      <h1 class="page-title">{{ $title ?? '' }}</h1>
      <div class="row">@yield('top')</div>
    </header>
    @yield('content')
  </div>
</div>
@include('partials.body-scripts')
@stack('scripts')
</body>
</html>
