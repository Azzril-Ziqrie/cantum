@extends('layouts.staff', ['title' => 'Tutup harian'])

@php
    $rp = fn ($n) => 'Rp ' . number_format((int) $n, 0, ',', '.');
    $t = $close['totals'];
    $filter = $close['filter'];
    $date = $close['date']->toDateString();
@endphp

@section('top')
  <a class="btn btn--light btn--small" href="{{ route('admin.reports', ['branch' => $branchId ?? 'all']) }}">Laporan</a>
  <a class="btn btn--small" href="{{ route('admin.reports.close.csv', ['branch' => $branchId ?? 'all', 'date' => $date]) }}">Ekspor CSV</a>
@endsection

@section('content')
<link rel="stylesheet" href="{{ asset('assets/reports.css') }}?v={{ filemtime(public_path('assets/reports.css')) }}">
<div class="content rp">
  @include('admin.reports._filters', ['action' => route('admin.reports.close'), 'withRange' => false])

  <p class="label rp-scope">{{ $branchName }} · {{ $close['date']->translatedFormat('l, d F Y') }}</p>
  <p class="muted">Cocokkan angka "Diterima gateway" dengan total settlement di dashboard Kasera untuk tanggal ini.</p>

  <section class="rp-kpis">
    <div class="card rp-hero"><p class="label">Diterima gateway</p><p class="rp-big num">{{ $rp($t['amount']) }}</p><p class="muted">{{ $t['count'] }} pesanan terbayar</p></div>
    <div class="card"><p class="label">Menunggu bayar</p><p class="rp-num num">{{ $rp($t['pending_amount']) }}</p><p class="muted">{{ $t['pending_count'] }} pesanan</p></div>
    <div class="card"><p class="label">Kedaluwarsa</p><p class="rp-num num">{{ $rp($t['expired_amount']) }}</p><p class="muted">{{ $t['expired_count'] }} pesanan</p></div>
    <div class="card"><p class="label">Refund dicatat</p><p class="rp-num num">{{ $rp($close['refunds']) }}</p></div>
  </section>

  @if ($t['late_count'] > 0)
    <p class="notice notice--warn"><b>{{ $t['late_count'] }} pembayaran ({{ $rp($t['late_amount']) }}) masuk setelah pesanan kedaluwarsa.</b> Dana sudah di gateway; tinjau dan kembalikan dari dashboard.</p>
  @endif

  <h2 class="section">Per metode pembayaran</h2>
  <div class="card rp-scroll">
    <table class="rp-table">
      <thead><tr><th>Metode</th><th class="r">Diterima</th><th class="r">Nominal</th><th class="r">Dibatalkan</th><th class="r">Terlambat</th><th class="r">Menunggu bayar</th><th class="r">Kedaluwarsa</th></tr></thead>
      <tbody>
        @forelse ($close['rows'] as $row)
          <tr><td>{{ \App\Services\Reports\ReportCsv::methodLabel($row['method']) }}</td>
            <td class="r num">{{ $row['count'] }}</td><td class="r num">{{ $rp($row['amount']) }}</td>
            <td class="r num">{{ $row['cancelled_count'] }} · {{ $rp($row['cancelled_amount']) }}</td>
            <td class="r num">{{ $row['late_count'] }} · {{ $rp($row['late_amount']) }}</td>
            <td class="r num">{{ $row['pending_count'] }} · {{ $rp($row['pending_amount']) }}</td>
            <td class="r num">{{ $row['expired_count'] }} · {{ $rp($row['expired_amount']) }}</td></tr>
        @empty
          <tr><td colspan="7" class="muted">Tidak ada transaksi pada tanggal ini.</td></tr>
        @endforelse
      </tbody>
      @if ($close['rows'] !== [])
        <tfoot><tr><td>Total</td><td class="r num">{{ $t['count'] }}</td><td class="r num">{{ $rp($t['amount']) }}</td>
          <td class="r num">{{ $t['cancelled_count'] }} · {{ $rp($t['cancelled_amount']) }}</td><td class="r num">{{ $t['late_count'] }} · {{ $rp($t['late_amount']) }}</td>
          <td class="r num">{{ $t['pending_count'] }} · {{ $rp($t['pending_amount']) }}</td><td class="r num">{{ $t['expired_count'] }} · {{ $rp($t['expired_amount']) }}</td></tr></tfoot>
      @endif
    </table>
  </div>
</div>
@endsection
