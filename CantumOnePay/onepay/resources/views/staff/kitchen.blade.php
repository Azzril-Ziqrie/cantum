@extends('layouts.staff', ['title' => 'Dapur dan bar'])

@section('top')
  <span class="conn" id="conn"><i></i><span id="connText">Menyambung</span></span>
  <button class="btn btn--light btn--small" type="button" id="soundBtn">Aktifkan suara</button>
@endsection

@section('content')
<link rel="stylesheet" href="{{ asset('assets/kitchen.css') }}?v={{ filemtime(public_path('assets/kitchen.css')) }}">
<div class="content">
  @if ($branches->isEmpty())
    <p class="notice">Akunmu belum ditugaskan ke cabang. Hubungi owner.</p>
  @else
    <nav class="station-tabs" aria-label="Stasiun">
      @foreach ($stations as $key => $label)
        <a href="{{ route('staff.kitchen', ['station' => $key]) }}" @if ($key === $station) aria-current="page" @endif>{{ $label }}</a>
      @endforeach
    </nav>

    @if ($branches->count() > 1)
      <div class="tabs" id="branchTabs" role="group" aria-label="Cabang">
        @foreach ($branches as $i => $branch)
          <button type="button" data-branch="{{ $branch->id }}" aria-pressed="{{ $i === 0 ? 'true' : 'false' }}">{{ $branch->name }}</button>
        @endforeach
      </div>
    @endif

    <div class="tickets" id="tickets" aria-live="polite"></div>
    <p class="muted" id="emptyMsg" hidden>Belum ada pesanan masuk untuk stasiun ini.</p>

    @if ($clientConfig['canToggle'])
      <section class="card stack soldout" id="menuPanel">
        <div class="row"><h2 class="section" style="margin:0">Menu habis</h2><input class="search" id="menuSearch" type="search" placeholder="Cari menu" aria-label="Cari menu"></div>
        <ul class="menu-toggles" id="menuList"></ul>
      </section>
    @endif
  @endif
</div>
@endsection

@push('scripts')
<script nonce="{{ $cspNonce ?? '' }}">window.KITCHEN = @json($clientConfig);</script>
<script src="{{ asset('assets/kitchen.js') }}?v={{ filemtime(public_path('assets/kitchen.js')) }}"></script>
@endpush
