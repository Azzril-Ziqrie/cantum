{{-- Kerangka halaman error. Tidak bergantung pada database atau sesi supaya tetap tampil saat 500/503. --}}
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex">
<meta name="theme-color" content="#f6f8fc">
<title>@yield('title') · {{ config('app.name', 'Cantum OnePay') }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:ital,wght@0,400;0,500;1,400&display=swap">
<link rel="stylesheet" href="{{ asset('assets/app.css') }}?v={{ @filemtime(public_path('assets/app.css')) }}">
<style>.err{min-height:100dvh;display:grid;align-content:center;gap:14px;padding:32px var(--pad)}.err-code{font-size:clamp(4rem,18vw,9rem);letter-spacing:-.08em;line-height:.9;color:var(--accent)}.err-actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:10px}.err .btn{min-height:48px;text-decoration:none}</style>
</head>
<body>
<main class="wrap err">
  <p class="label">{{ config('app.name', 'Cantum OnePay') }}</p>
  <p class="err-code" aria-hidden="true">@yield('code')</p>
  <h1 class="display" style="margin:0">@yield('headline')</h1>
  <p class="muted">@yield('detail')</p>
  <div class="err-actions">@yield('actions')</div>
</main>
<script src="{{ asset('assets/ui.js') }}?v={{ filemtime(public_path('assets/ui.js')) }}" defer></script>
</body>
</html>
