@extends('layouts.staff', ['title' => 'Denah meja'])

@section('top')
  <span class="conn" id="conn"><i></i><span id="connText">Menyambung</span></span>
  <button class="btn btn--light btn--small" type="button" id="openBtn" hidden></button>
  <button class="btn btn--light btn--small" type="button" id="soundBtn">Aktifkan suara</button>
@endsection

@section('content')
<link rel="stylesheet" href="{{ asset('assets/kitchen.css') }}?v={{ filemtime(public_path('assets/kitchen.css')) }}">
<link rel="stylesheet" href="{{ asset('assets/iso-floor.css') }}?v={{ filemtime(public_path('assets/iso-floor.css')) }}">
<div class="content">
  @if ($branches->isEmpty())
    <p class="notice">Akunmu belum ditugaskan ke cabang. Hubungi owner.</p>
  @else
    <div class="tabs" id="branchTabs" role="group" aria-label="Cabang">
      @foreach ($branches as $i => $branch)
        <button type="button" data-branch="{{ $branch->id }}" aria-pressed="{{ $branch->id === $clientConfig['branch'] ? 'true' : 'false' }}">{{ $branch->name }}</button>
      @endforeach
    </div>

    <ul class="rule-chips" id="ruleChips" aria-label="Aturan khusus cabang ini" hidden></ul>

    <p class="notice notice--warn" id="closedBanner" hidden>Cabang ini sedang <b>tutup</b>. Pelanggan belum bisa membuat pesanan.</p>

    <section id="reviewBox" class="stack" hidden>
      <p class="notice notice--warn"><b>Perlu ditinjau:</b> pembayaran masuk setelah pesanan kedaluwarsa atau dibatalkan. Dana perlu dikembalikan.
        <a id="refundsLink" class="link-inline" href="#" hidden>Selesaikan di halaman refund</a></p>
      <ul id="reviewList" class="stack"></ul>
    </section>

    <div class="plan">
      <div class="plan-main">
        <div class="row plan-head">
          <ul class="legend" aria-label="Keterangan warna">
            <li><i class="dot dot--empty"></i>Kosong</li><li><i class="dot dot--pending"></i>Menunggu bayar</li>
            <li><i class="dot dot--new"></i>Baru</li><li><i class="dot dot--preparing"></i>Diproses</li>
            <li><i class="dot dot--ready"></i>Siap disajikan</li><li><i class="dot dot--served"></i>Disajikan</li>
          </ul>
        </div>
        <div class="floor-wrap" id="floorWrap"><div class="floor" id="floor"></div></div>
      </div>

      <aside class="card detail" id="detail" aria-live="polite">
        <p class="muted" id="detailEmpty">Pilih meja untuk melihat pesanannya.</p>
        <div id="detailBody" class="stack" hidden></div>
      </aside>
    </div>

    <h2 class="section">Antrian pesanan</h2>
    <div class="tabs" id="statusTabs" role="group" aria-label="Filter status">
      <button type="button" data-status="all" aria-pressed="true">Semua</button>
      <button type="button" data-status="paid" aria-pressed="false">Baru</button>
      <button type="button" data-status="preparing" aria-pressed="false">Diproses</button>
      <button type="button" data-status="served" aria-pressed="false">Disajikan</button>
    </div>
    <div class="board" id="board" aria-live="polite"></div>
    <p class="muted" id="emptyMsg" hidden>Belum ada pesanan aktif.</p>

    <section class="card stack soldout" id="menuPanel" hidden>
      <div class="row"><h2 class="section" style="margin:0">Menu habis</h2><input class="search" id="menuSearch" type="search" placeholder="Cari menu" aria-label="Cari menu"></div>
      <p class="muted">Menu yang ditandai habis langsung hilang dari halaman pelanggan di cabang ini.</p>
      <ul class="menu-toggles" id="menuList"></ul>
    </section>
  @endif
</div>
@endsection

@push('scripts')
<script nonce="{{ $cspNonce ?? '' }}">window.DASH = @json($clientConfig);</script>
<script src="{{ asset('assets/dither.js') }}"></script>
<script src="{{ asset('assets/table-sprite.js') }}?v={{ filemtime(public_path('assets/table-sprite.js')) }}"></script>
<script src="{{ asset('assets/dashboard.js') }}?v={{ filemtime(public_path('assets/dashboard.js')) }}"></script>
@endpush
