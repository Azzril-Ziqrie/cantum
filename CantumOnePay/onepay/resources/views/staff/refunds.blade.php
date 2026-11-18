@extends('layouts.staff', ['title' => 'Refund dan pembatalan'])

@section('top')
  <span class="conn" id="conn"><i></i><span id="connText">Menyambung</span></span>
@endsection

@section('content')
<link rel="stylesheet" href="{{ asset('assets/kitchen.css') }}?v={{ filemtime(public_path('assets/kitchen.css')) }}">
<div class="content">
  @if ($branches->isEmpty())
    <p class="notice">Akunmu belum ditugaskan ke cabang. Hubungi owner.</p>
  @else
    @if ($branches->count() > 1)
      <div class="tabs" id="branchTabs" role="group" aria-label="Cabang">
        @foreach ($branches as $i => $branch)
          <button type="button" data-branch="{{ $branch->id }}" aria-pressed="{{ $i === 0 ? 'true' : 'false' }}">{{ $branch->name }}</button>
        @endforeach
      </div>
    @endif

    <h2 class="section">Pengajuan pembatalan</h2>
    <p class="muted">Diajukan kasir, menunggu persetujuanmu. Menyetujui akan membatalkan pesanan dan mencatat refund.</p>
    <div class="refund-grid" id="requests"></div>

    <h2 class="section">Perlu ditinjau</h2>
    <p class="muted">Pembayaran masuk setelah pesanan kedaluwarsa atau dibatalkan. Kembalikan dana lewat dashboard Kasera, lalu catat di sini.</p>
    <div class="refund-grid" id="review"></div>

    <h2 class="section">Refund tertunda</h2>
    <p class="muted">Pesanan yang dibatalkan setelah dibayar. Tandai selesai setelah dana dikembalikan.</p>
    <div class="refund-grid" id="pending"></div>

    <h2 class="section">Riwayat refund</h2>
    <div class="refund-grid" id="done"></div>
  @endif
</div>
@endsection

@push('scripts')
<script nonce="{{ $cspNonce ?? '' }}">window.REFUNDS = @json($clientConfig);</script>
<script src="{{ asset('assets/refunds.js') }}?v={{ filemtime(public_path('assets/refunds.js')) }}"></script>
@endpush
