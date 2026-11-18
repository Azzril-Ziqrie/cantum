@extends('layouts.staff', ['title' => 'Menu'])

@php
    $tabs = ['items' => 'Menu', 'branch' => 'Cabang', 'categories' => 'Kategori', 'bulk' => 'Ubah massal', 'csv' => 'Impor / ekspor'];
    $ownerOnly = ['categories', 'bulk'];
    $rp = fn ($n) => 'Rp ' . number_format($n, 0, ',', '.');
@endphp

@section('top')
  @if ($isOwner)<a class="btn btn--small" href="{{ route('admin.menu.items.create') }}">Tambah menu</a>@endif
@endsection

@section('content')
<div class="content am">
  @include('admin.menu._flash')

  <nav class="tabs" aria-label="Bagian menu">
    @foreach ($tabs as $key => $label)
      @continue(in_array($key, $ownerOnly, true) && !$isOwner)
      <a class="tab" href="{{ route('admin.menu', ['tab' => $key, 'branch' => $branch?->id]) }}" aria-pressed="{{ $tab === $key ? 'true' : 'false' }}">{{ $label }}</a>
    @endforeach
  </nav>

  {{-- ===== Menu ===== --}}
  @if ($tab === 'items')
    @forelse ($categories as $category)
      @php($rows = $items->where('category_id', $category->id))
      @continue($rows->isEmpty())
      <h2 class="section">{{ $category->name }}@unless ($category->is_active) <span class="tag">nonaktif</span>@endunless</h2>
      <div class="card am-list">
        @foreach ($rows as $item)
          <div class="am-row">
            @if ($item->photo)<img class="am-thumb" src="{{ $item->photoUrl() }}" alt="" width="48" height="48" loading="lazy">@else<span class="am-thumb am-thumb--empty" aria-hidden="true"></span>@endif
            <div class="am-grow">
              <b>{{ $item->name }}</b>
              <small class="muted">
                {{ $item->station === 'bar' ? 'Bar' : 'Dapur' }}
                @if ($item->is_best_seller) · Best seller @endif
                @if ($item->is_bundle) · Paket @endif
                @if ($item->available_from) · {{ substr($item->available_from, 0, 5) }}–{{ substr($item->available_until, 0, 5) }} @endif
                @unless ($item->is_active) · nonaktif @endunless
              </small>
            </div>
            <span class="num">{{ $rp($item->price) }}</span>
            @if ($isOwner)<a class="btn btn--light btn--small" href="{{ route('admin.menu.items.edit', $item) }}">Ubah</a>@endif
          </div>
        @endforeach
      </div>
    @empty
      <p class="notice">Belum ada kategori. Buat kategori dulu di tab Kategori{{ $isOwner ? '' : ' (hubungi owner)' }}.</p>
    @endforelse
  @endif

  {{-- ===== Per cabang ===== --}}
  @if ($tab === 'branch')
    @if (!$branch)
      <p class="notice">Akunmu belum ditugaskan ke cabang.</p>
    @else
      <div class="tabs" aria-label="Cabang">
        @foreach ($branches as $b)
          <a class="tab" href="{{ route('admin.menu', ['tab' => 'branch', 'branch' => $b->id]) }}" aria-pressed="{{ $b->id === $branch->id ? 'true' : 'false' }}">{{ $b->name }}</a>
        @endforeach
      </div>
      <p class="muted">Tombol Habis dan harga khusus hanya berlaku di {{ $branch->name }}. Kosongkan harga untuk memakai harga dasar.</p>

      <div class="card am-list" id="branchList">
        @foreach ($items as $item)
          @php($setting = $branchItems[$item->id] ?? null)
          @php($available = $setting ? (bool) $setting->is_available : true)
          <div class="am-row" data-url="{{ route('admin.menu.branch-items.update', [$branch, $item]) }}">
            <div class="am-grow"><b>{{ $item->name }}</b><small class="muted">Dasar {{ $rp($item->price) }}</small></div>
            <input class="am-price" type="number" min="0" step="100" inputmode="numeric" placeholder="Harga cabang" aria-label="Harga khusus {{ $item->name }}" value="{{ $setting->price_override ?? '' }}">
            <button type="button" class="btn btn--small am-toggle {{ $available ? '' : 'btn--light' }}" data-available="{{ $available ? 1 : 0 }}" aria-pressed="{{ $available ? 'true' : 'false' }}">{{ $available ? 'Tersedia' : 'Habis' }}</button>
          </div>
        @endforeach
      </div>

      @if ($branches->count() > 1)
        <form class="card am-inline" method="post" action="{{ route('admin.menu.branches.copy', $branch) }}" data-confirm="Pengaturan ketersediaan dan harga {{ $branch->name }} akan diganti. Lanjutkan?" data-confirm-title="Salin pengaturan cabang?" data-confirm-label="Ya, ganti">
          @csrf
          <label class="am-grow">Salin ketersediaan dan harga cabang dari
            <select name="source">@foreach ($branches->where('id', '!=', $branch->id) as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach</select>
          </label>
          <button class="btn btn--light btn--small" type="submit">Salin</button>
        </form>
      @endif
    @endif
  @endif

  {{-- ===== Kategori ===== --}}
  @if ($tab === 'categories' && $isOwner)
    <div class="card am-list">
      @foreach ($categories as $category)
        <form class="am-row" method="post" action="{{ route('admin.menu.categories.update', $category) }}">
          @csrf @method('PUT')
          <input class="am-grow" name="name" value="{{ $category->name }}" required maxlength="80" aria-label="Nama kategori">
          <input class="am-narrow" type="number" name="sort_order" value="{{ $category->sort_order }}" min="0" aria-label="Urutan">
          <label class="am-check"><input type="checkbox" name="is_active" value="1" @checked($category->is_active)> Aktif</label>
          <button class="btn btn--light btn--small" type="submit">Simpan</button>
          <button class="btn btn--light btn--small" type="submit" form="del-cat-{{ $category->id }}" title="{{ $category->items_count }} menu">Hapus</button>
        </form>
        <form id="del-cat-{{ $category->id }}" method="post" action="{{ route('admin.menu.categories.destroy', $category) }}">@csrf @method('DELETE')</form>
      @endforeach
    </div>
    <form class="card am-inline" method="post" action="{{ route('admin.menu.categories.store') }}">
      @csrf
      <input class="am-grow" name="name" placeholder="Kategori baru, mis. Dessert" required maxlength="80">
      <button class="btn btn--small" type="submit">Tambah kategori</button>
    </form>
  @endif

  {{-- ===== Ubah massal ===== --}}
  @if ($tab === 'bulk' && $isOwner)
    <form class="card stack" method="post" action="{{ route('admin.menu.bulk.preview') }}">
      @csrf
      <div class="am-inline">
        <label>Kategori
          <select name="category_id"><option value="">Semua kategori</option>@foreach ($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select>
        </label>
        <label>Cara
          <select name="mode"><option value="percent">Persen (%)</option><option value="amount">Nominal (Rp)</option></select>
        </label>
        <label>Nilai (boleh minus)
          <input type="number" name="value" required step="1" placeholder="mis. 5 atau -2000">
        </label>
        <button class="btn btn--small" type="submit">Pratinjau</button>
      </div>
      <p class="muted">Persen dibulatkan ke kelipatan Rp 100. Tidak ada yang berubah sebelum kamu menerapkan.</p>
    </form>

    @if ($preview)
      <div class="card am-list">
        @forelse ($preview['rows'] as $row)
          <div class="am-row"><span class="am-grow">{{ $row['name'] }}</span><span class="num muted">{{ $rp($row['old']) }}</span><span aria-hidden="true">→</span><b class="num">{{ $rp($row['new']) }}</b></div>
        @empty
          <p class="muted">Tidak ada harga yang berubah.</p>
        @endforelse
      </div>
      @if (count($preview['rows']))
        <form method="post" action="{{ route('admin.menu.bulk.apply') }}">
          @csrf
          <input type="hidden" name="category_id" value="{{ $preview['input']['category_id'] ?? '' }}">
          <input type="hidden" name="mode" value="{{ $preview['input']['mode'] }}">
          <input type="hidden" name="value" value="{{ $preview['input']['value'] }}">
          <button class="btn" type="submit">Terapkan ke {{ count($preview['rows']) }} menu</button>
        </form>
      @endif
    @endif
  @endif

  {{-- ===== CSV ===== --}}
  @if ($tab === 'csv')
    <div class="card stack">
      <h2 class="section" style="margin:0">Ekspor</h2>
      <p class="muted">Semua menu dalam satu berkas CSV (bisa dibuka di Excel).</p>
      <p><a class="btn btn--light btn--small" href="{{ route('admin.menu.export') }}">Unduh CSV</a></p>
    </div>
    @if ($isOwner)
      <form class="card stack" method="post" action="{{ route('admin.menu.import') }}" enctype="multipart/form-data">
        @csrf
        <h2 class="section" style="margin:0">Impor</h2>
        <p class="muted">Kolom: <code>category, name, description, price, cost, station, best_seller, active, sort_order</code>. Menu dicocokkan lewat kategori + nama: yang sudah ada diperbarui, yang belum dibuat. Satu baris salah membatalkan seluruh impor.</p>
        <input type="file" name="file" accept=".csv,text/csv" required>
        <button class="btn btn--small" type="submit">Impor</button>
      </form>
    @endif
  @endif
</div>
@endsection

@push('scripts')
<link rel="stylesheet" href="{{ asset('assets/admin-menu.css') }}?v={{ filemtime(public_path('assets/admin-menu.css')) }}">
<script src="{{ asset('assets/admin-menu.js') }}?v={{ filemtime(public_path('assets/admin-menu.js')) }}"></script>
@endpush
