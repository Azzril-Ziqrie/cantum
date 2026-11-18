<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Stiker meja · {{ $brand->name }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500&display=swap">
<link rel="stylesheet" href="{{ asset('assets/floor-editor.css') }}?v={{ filemtime(public_path('assets/floor-editor.css')) }}">
</head>
<body class="sheet-body">
<header class="sheet-head">
  <div>
    <h1>Stiker meja</h1>
    <p>{{ $stickers->count() }} stiker. Cetak di kertas stiker A4 dengan skala 100%, lalu potong tiap kotak.</p>
  </div>
  <button type="button" data-print>Cetak</button>
</header>

@if ($stickers->isEmpty())
  <p class="sheet-empty">Belum ada meja aktif untuk dicetak.</p>
@endif

<main class="sheet-grid">
  @foreach ($stickers as $sticker)
    <figure class="sticker" aria-label="Stiker meja {{ $sticker['label'] }}">{!! $sticker['svg'] !!}</figure>
  @endforeach
</main>
<script src="{{ asset('assets/ui.js') }}?v={{ filemtime(public_path('assets/ui.js')) }}" defer></script>
</body>
</html>
