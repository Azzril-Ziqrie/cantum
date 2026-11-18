<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Laporan penjualan {{ $filter->from->toDateString() }} – {{ $filter->to->toDateString() }} · {{ $brand->name }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500&display=swap">
<link rel="stylesheet" href="{{ asset('assets/app.css') }}?v={{ filemtime(public_path('assets/app.css')) }}">
<link rel="stylesheet" href="{{ asset('assets/reports.css') }}?v={{ filemtime(public_path('assets/reports.css')) }}">
</head>
<body class="rp-print">
<main class="rp wrap wide">
  <div class="rp-print-head">
    <div>
      <p class="label">{{ $brand->name }} · Laporan penjualan</p>
      <h1 class="display">{{ $branchName }}</h1>
      <p class="muted">{{ $filter->from->translatedFormat('d M Y') }} – {{ $filter->to->translatedFormat('d M Y') }} · dicetak {{ now()->translatedFormat('d M Y H:i') }}</p>
    </div>
    <div class="rp-noprint">
      <button class="btn" type="button" data-print>Cetak / simpan PDF</button>
      <p class="muted">Pilih "Simpan sebagai PDF" di dialog cetak.</p>
    </div>
  </div>

  @include('admin.reports._report')
</main>
<div class="rp-tip" id="rpTip" role="tooltip" hidden></div>
<script src="{{ asset('assets/reports.js') }}?v={{ filemtime(public_path('assets/reports.js')) }}" defer></script>
<script src="{{ asset('assets/ui.js') }}?v={{ filemtime(public_path('assets/ui.js')) }}" defer></script>
</body>
</html>
