@extends('layouts.app', ['title' => $brand->name . ' · Meja ' . $table->label, 'brandName' => $brand->name])

@section('bar')
  <span class="chip">Meja {{ $table->label }}</span>
@endsection

@section('content')
<main class="wrap">
  <p class="label">{{ $table->branch->name }}</p>
  <h1 class="display" style="margin-bottom:8px">Pesan dari meja, bayar langsung.</h1>
  @if ($brand->tagline)<p class="muted brand-tagline">{{ $brand->tagline }}</p>@endif

  <p class="info-chips">
    <span class="chip {{ $schedule['orderable'] ? 'chip--open' : 'chip--closed' }}">{{ $schedule['orderable'] ? 'Buka' : 'Tutup' }}@if ($schedule['hours']) · {{ $schedule['hours'] }}@endif</span>
    @if ($prepMinutes > 0)<span class="chip">Estimasi penyajian ±{{ $prepMinutes }} mnt</span>@endif
  </p>

  @unless ($schedule['orderable'])
    <p class="notice notice--warn" role="status">{{ $schedule['message'] }} Kamu masih bisa melihat menu, tapi pesanan belum bisa dibayar.</p>
  @endunless

  @include('partials.promos', ['promos' => $promos])

  @if (empty($menu))
    <div class="card stack empty-state">
      <h2 class="section" style="margin:0">Menu belum tersedia.</h2>
      <p class="muted">Belum ada menu yang dijual di cabang ini sekarang. Minta bantuan staf untuk memesan.</p>
    </div>
  @endif

  @php($currentCategory = null)
  @foreach ($menu as $item)
    @if ($item['category_id'] !== $currentCategory)
      @php($currentCategory = $item['category_id'])
      <h2 class="section">{{ $categories[$currentCategory] ?? '' }}</h2>
    @endif
    <article class="menu-item @if ($item['photo_url'])menu-item--photo @endif">
      @if ($item['photo_url'])<img class="menu-photo" src="{{ $item['photo_url'] }}" alt="" loading="lazy" width="96" height="96">@endif
      <h3>{{ $item['name'] }}@if ($item['is_best_seller'])<span class="tag">Best seller</span>@endif</h3>
      <span class="price num">Rp {{ number_format($item['price'], 0, ',', '.') }}</span>
      <p>{{ $item['description'] }}@if ($item['is_bundle'] && $item['components']) Isi: {{ collect($item['components'])->map(fn ($c) => $c['qty'].'× '.$c['name'])->join(', ') }}.@endif</p>
      <button class="btn btn--small add" type="button" data-id="{{ $item['id'] }}">Tambah</button>
    </article>
  @endforeach
</main>

<div class="cartbar" id="cartbar" hidden>
  <span><b id="cartCount">0</b> item · <b class="num" id="cartTotal">Rp 0</b></span>
  <button class="btn btn--small" type="button" id="openCart">Lihat pesanan</button>
</div>

<div class="sheet" id="sheet" hidden>
  <div class="sheet-panel stack" role="dialog" aria-modal="true" aria-label="Pesanan">
    <div class="row"><h2 class="section" id="sheetTitle" style="margin:0">Pesanan</h2><button class="btn btn--light btn--small" type="button" id="closeSheet">Tutup</button></div>
    <div id="sheetBody" class="stack"></div>
  </div>
</div>
@endsection

@push('scripts')
<link rel="stylesheet" href="{{ asset('assets/menu-photo.css') }}?v={{ filemtime(public_path('assets/menu-photo.css')) }}">
<script nonce="{{ $cspNonce ?? '' }}">window.ONEPAY = @json($clientConfig + ['menu' => $menu]);</script>
<link rel="stylesheet" href="{{ asset('assets/customer.css') }}?v={{ filemtime(public_path('assets/customer.css')) }}">
<script src="{{ asset('assets/menu.js') }}?v={{ filemtime(public_path('assets/menu.js')) }}"></script>
@endpush
