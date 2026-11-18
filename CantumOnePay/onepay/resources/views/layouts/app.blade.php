<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex">
<meta name="theme-color" content="#f6f8fc">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $title ?? 'Cantum OnePay' }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:ital,wght@0,400;0,500;1,400&display=swap">
@include('partials.head-assets')
</head>
<body>
<header class="bar {{ ($wide ?? false) ? 'bar--wide' : '' }}">
  <a class="brand" href="{{ url()->current() }}">{{ $brandName ?? 'Cantum OnePay' }}</a>
  <div class="row">@yield('bar')</div>
</header>

@yield('content')

@include('partials.body-scripts')
@stack('scripts')
</body>
</html>
