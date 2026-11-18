@extends('layouts.staff', ['title' => 'Semua cabang'])

@section('top')
  <span class="conn" id="conn"><i></i><span id="connText">Menyambung</span></span>
@endsection

@section('content')
<link rel="stylesheet" href="{{ asset('assets/overview.css') }}?v={{ filemtime(public_path('assets/overview.css')) }}">
<div class="content">
  <p class="muted">Ringkasan hari ini untuk setiap cabang. Klik nama cabang untuk membuka dashboard-nya.</p>
  <div class="ov-grid" id="grid" aria-live="polite"></div>
  <p class="muted" id="emptyMsg" hidden>Belum ada cabang aktif.</p>
</div>
@endsection

@push('scripts')
<script nonce="{{ $cspNonce ?? '' }}">window.OVERVIEW = @json($config);</script>
<script src="{{ asset('assets/overview.js') }}?v={{ filemtime(public_path('assets/overview.js')) }}"></script>
@endpush
