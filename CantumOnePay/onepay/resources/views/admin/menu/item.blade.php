@extends('layouts.staff', ['title' => $item->exists ? 'Ubah menu' : 'Menu baru'])

@php
    $time = fn ($value) => $value ? substr($value, 0, 5) : '';
    $rp = fn ($n) => 'Rp ' . number_format($n, 0, ',', '.');
    $action = $item->exists ? route('admin.menu.items.update', $item) : route('admin.menu.items.store');
    $isBundle = old('is_bundle', $item->is_bundle);
    $components = old('components', $item->bundleComponents->map(fn ($c) => ['item_id' => $c->component_item_id, 'qty' => $c->qty])->all());
@endphp

@section('top')
  <a class="btn btn--light btn--small" href="{{ route('admin.menu') }}">Kembali</a>
@endsection

@section('content')
<div class="content am">
  @include('admin.menu._flash')

  <form class="card stack" method="post" action="{{ $action }}" enctype="multipart/form-data">
    @csrf @if ($item->exists) @method('PUT') @endif

    <div class="am-grid">
      <label>Nama<input name="name" value="{{ old('name', $item->name) }}" required maxlength="120"></label>
      <label>Kategori
        <select name="category_id" required>@foreach ($categories as $c)<option value="{{ $c->id }}" @selected(old('category_id', $item->category_id) == $c->id)>{{ $c->name }}</option>@endforeach</select>
      </label>
      <label class="am-wide">Deskripsi<textarea name="description" maxlength="500" rows="2">{{ old('description', $item->description) }}</textarea></label>
      <label>Harga dasar (Rp)<input type="number" name="price" min="0" step="100" required value="{{ old('price', $item->price) }}"></label>
      <label>HPP (Rp, diisi manual)<input type="number" name="cost" min="0" step="100" value="{{ old('cost', $item->cost ?? 0) }}"></label>
      <label>Disiapkan di
        <select name="station"><option value="kitchen" @selected(old('station', $item->station) === 'kitchen')>Dapur</option><option value="bar" @selected(old('station', $item->station) === 'bar')>Bar</option></select>
      </label>
      <label>Urutan tampil<input type="number" name="sort_order" min="0" value="{{ old('sort_order', $item->sort_order ?? 0) }}"></label>
      <label>Tampil mulai jam<input type="time" name="available_from" value="{{ old('available_from', $time($item->available_from)) }}"></label>
      <label>Tampil sampai jam<input type="time" name="available_until" value="{{ old('available_until', $time($item->available_until)) }}"></label>
    </div>
    <p class="muted">Kosongkan jam bila menu tampil sepanjang hari. Contoh menu sarapan: 06:00 sampai 11:00.</p>

    <div class="am-photo">
      @if ($item->photo)<img class="am-thumb am-thumb--big" src="{{ $item->photoUrl() }}" alt="Foto {{ $item->name }}" width="96" height="96">@endif
      <label class="am-grow">Foto (JPG, PNG, WebP, maks 2 MB)<input type="file" name="photo" accept="image/jpeg,image/png,image/webp"></label>
      @if ($item->photo)<label class="am-check"><input type="checkbox" name="remove_photo" value="1"> Hapus foto</label>@endif
    </div>

    <div class="am-flags">
      <label class="am-check"><input type="checkbox" name="is_best_seller" value="1" @checked(old('is_best_seller', $item->is_best_seller))> Best seller</label>
      <label class="am-check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $item->is_active ?? true))> Aktif</label>
      <label class="am-check"><input type="checkbox" name="is_bundle" value="1" id="isBundle" @checked($isBundle)> Menu paket</label>
    </div>

    <fieldset class="am-bundle stack" id="bundleBox" @unless ($isBundle) hidden @endunless>
      <legend class="label">Isi paket (tampil di dapur dan struk; harga paket = harga dasar di atas)</legend>
      <div id="bundleRows" class="stack">
        @foreach ($components as $i => $component)
          <div class="am-inline">
            <select name="components[{{ $i }}][item_id]" class="am-grow">@foreach ($plainItems as $p)<option value="{{ $p->id }}" @selected(($component['item_id'] ?? null) == $p->id)>{{ $p->name }}</option>@endforeach</select>
            <input class="am-narrow" type="number" name="components[{{ $i }}][qty]" min="1" max="99" value="{{ $component['qty'] ?? 1 }}" aria-label="Jumlah">
            <button type="button" class="btn btn--light btn--small am-remove">Hapus</button>
          </div>
        @endforeach
      </div>
      <button type="button" class="btn btn--light btn--small" id="addComponent">Tambah isi</button>
      <template id="componentTemplate">
        <div class="am-inline">
          <select data-name="components[__i__][item_id]" class="am-grow">@foreach ($plainItems as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select>
          <input class="am-narrow" type="number" data-name="components[__i__][qty]" min="1" max="99" value="1" aria-label="Jumlah">
          <button type="button" class="btn btn--light btn--small am-remove">Hapus</button>
        </div>
      </template>
    </fieldset>

    <div class="am-inline">
      <button class="btn" type="submit">{{ $item->exists ? 'Simpan' : 'Buat menu' }}</button>
    </div>
  </form>

  @if ($item->exists)
    <h2 class="section">Pilihan modifier</h2>
    <p class="muted">Ukuran, topping, level gula atau pedas. Grup wajib berarti pelanggan harus memilih satu.</p>

    @foreach ($item->modifierGroups as $group)
      <div class="card stack">
        <form class="am-inline" method="post" action="{{ route('admin.menu.groups.update', $group) }}">
          @csrf @method('PUT')
          <input class="am-grow" name="name" value="{{ $group->name }}" required maxlength="80" aria-label="Nama grup">
          <label class="am-check"><input type="checkbox" name="required" value="1" @checked($group->required)> Wajib</label>
          <button class="btn btn--light btn--small" type="submit">Simpan</button>
          <button class="btn btn--light btn--small" type="submit" form="del-group-{{ $group->id }}">Hapus grup</button>
        </form>
        <form id="del-group-{{ $group->id }}" method="post" action="{{ route('admin.menu.groups.destroy', $group) }}">@csrf @method('DELETE')</form>

        @foreach ($group->modifiers as $modifier)
          <form class="am-inline" method="post" action="{{ route('admin.menu.modifiers.update', $modifier) }}">
            @csrf @method('PUT')
            <input class="am-grow" name="name" value="{{ $modifier->name }}" required maxlength="80" aria-label="Nama opsi">
            <input class="am-price" type="number" name="price_delta" value="{{ $modifier->price_delta }}" step="500" aria-label="Tambahan harga">
            <button class="btn btn--light btn--small" type="submit">Simpan</button>
            <button class="btn btn--light btn--small" type="submit" form="del-mod-{{ $modifier->id }}">Hapus</button>
          </form>
          <form id="del-mod-{{ $modifier->id }}" method="post" action="{{ route('admin.menu.modifiers.destroy', $modifier) }}">@csrf @method('DELETE')</form>
        @endforeach

        <form class="am-inline" method="post" action="{{ route('admin.menu.modifiers.store', $group) }}">
          @csrf
          <input class="am-grow" name="name" placeholder="Opsi baru, mis. Besar" required maxlength="80">
          <input class="am-price" type="number" name="price_delta" value="0" step="500" aria-label="Tambahan harga">
          <button class="btn btn--small" type="submit">Tambah opsi</button>
        </form>
      </div>
    @endforeach

    <form class="card am-inline" method="post" action="{{ route('admin.menu.groups.store', $item) }}">
      @csrf
      <input class="am-grow" name="name" placeholder="Grup baru, mis. Ukuran atau Level gula" required maxlength="80">
      <label class="am-check"><input type="checkbox" name="required" value="1"> Wajib</label>
      <button class="btn btn--small" type="submit">Tambah grup</button>
    </form>

    <form method="post" action="{{ route('admin.menu.items.destroy', $item) }}" data-confirm="Menu yang pernah dipesan hanya dinonaktifkan supaya riwayatnya tetap ada." data-confirm-title="Hapus menu ini?" data-confirm-label="Hapus" data-confirm-danger">
      @csrf @method('DELETE')
      <button class="btn btn--light btn--small" type="submit">Hapus menu</button>
    </form>
  @endif
</div>
@endsection

@push('scripts')
<link rel="stylesheet" href="{{ asset('assets/admin-menu.css') }}?v={{ filemtime(public_path('assets/admin-menu.css')) }}">
<script src="{{ asset('assets/admin-menu.js') }}?v={{ filemtime(public_path('assets/admin-menu.js')) }}"></script>
@endpush
