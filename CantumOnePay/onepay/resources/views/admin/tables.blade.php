@extends('layouts.staff', ['title' => 'Meja dan denah'])

@section('top')
  <span class="saved" id="saveState" role="status" aria-live="polite">Tersimpan otomatis</span>
  <a class="btn btn--light btn--small" id="sheetLink" href="{{ route('admin.tables.sheet', ['branch' => $config['branch']]) }}" target="_blank" rel="noopener">Cetak stiker semua meja</a>
@endsection

@section('content')
<div class="content editor-page">
  @if ($branches->isEmpty())
    <p class="notice">Akunmu belum ditugaskan ke cabang. Hubungi owner.</p>
  @else
    @if ($branches->count() > 1)
      <div class="tabs" id="branchTabs" role="group" aria-label="Cabang">
        @foreach ($branches as $branch)
          <button type="button" data-branch="{{ $branch->id }}" aria-pressed="{{ $branch->id === $config['branch'] ? 'true' : 'false' }}">{{ $branch->name }}</button>
        @endforeach
      </div>
    @endif

    <div class="editor-bar">
      <p class="muted" id="countLabel" aria-live="polite"></p>
      <div class="actions">
        <button class="btn btn--small" type="button" id="addTable">+ Meja</button>
        <button class="btn btn--light btn--small" type="button" id="addBulk">+ Banyak meja</button>
        <select class="select" id="addElement" aria-label="Tambah elemen dekoratif">
          <option value="">+ Elemen denah</option>
          <option value="wall">Dinding</option>
          <option value="door">Pintu</option>
          <option value="cashier">Kasir</option>
          <option value="bar">Bar</option>
        </select>
        @if ($branches->count() > 1)
          <button class="btn btn--light btn--small" type="button" id="copyLayout">Salin denah dari cabang lain</button>
        @endif
      </div>
    </div>

    <div class="editor-grid">
      <div class="floor-wrap editor" id="floorWrap">
        <div class="floor" id="floor"></div>
      </div>

      <aside class="card props" id="props" aria-live="polite">
        <p class="muted" id="propsEmpty">Pilih meja atau elemen untuk mengubah propertinya. Geser untuk memindahkan; tombol panah menggeser 10 titik.</p>
        <div id="propsBody" class="stack" hidden></div>
      </aside>
    </div>
  @endif
</div>

<dialog id="dialog" class="dialog">
  <form method="dialog" class="stack" id="dialogForm">
    <h2 class="section" id="dialogTitle" style="margin:0"></h2>
    <div class="stack" id="dialogFields"></div>
    <p class="notice notice--warn" id="dialogError" hidden role="alert"></p>
    <div class="actions">
      <button class="btn" type="submit" id="dialogOk">Simpan</button>
      <button class="btn btn--light" type="button" id="dialogCancel">Batal</button>
    </div>
  </form>
</dialog>
@endsection

@push('scripts')
<link rel="stylesheet" href="{{ asset('assets/floor-editor.css') }}?v={{ filemtime(public_path('assets/floor-editor.css')) }}">
<script src="{{ asset('assets/dither.js') }}"></script>
<script src="{{ asset('assets/table-sprite.js') }}?v={{ filemtime(public_path('assets/table-sprite.js')) }}"></script>
<script nonce="{{ $cspNonce ?? '' }}">window.EDITOR = @json($config);</script>
<script src="{{ asset('assets/floor-editor.js') }}?v={{ filemtime(public_path('assets/floor-editor.js')) }}"></script>
@endpush
