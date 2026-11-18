@extends('layouts.staff', ['title' => 'Diskon dan promo'])

@php
    $dayNames = [1 => 'Sen', 2 => 'Sel', 3 => 'Rab', 4 => 'Kam', 5 => 'Jum', 6 => 'Sab', 7 => 'Min'];
    $rupiah = fn (int $n) => 'Rp ' . number_format($n, 0, ',', '.');
@endphp

@section('top')
  <a class="btn btn--small" href="{{ route('admin.discounts.create') }}">Promo baru</a>
@endsection

@section('content')
<link rel="stylesheet" href="{{ asset('assets/discounts.css') }}?v={{ filemtime(public_path('assets/discounts.css')) }}">
<div class="content">
  @if (session('status'))<p class="notice" role="status">{{ session('status') }}</p>@endif

  @if ($discounts->isEmpty())
    <div class="card stack">
      <p>Belum ada promo.</p>
      <p class="muted">Buat promo otomatis (berlaku tanpa kode, mis. happy hour) atau promo dengan kode yang diketik pelanggan di keranjang.</p>
    </div>
  @else
    <table class="disc-table">
      <thead><tr><th>Promo</th><th>Potongan</th><th>Berlaku</th><th>Pemakaian</th><th>Cabang</th><th>Status</th><th></th></tr></thead>
      <tbody>
      @foreach ($discounts as $discount)
        <tr>
          <td><b>{{ $discount->name }}</b>
            <small class="code">{{ $discount->code ? 'Kode ' . $discount->code : 'Otomatis' }}</small></td>
          <td>{{ $discount->summary() }}
            <small>{{ ['order' => 'Seluruh pesanan', 'items' => 'Menu tertentu', 'categories' => 'Kategori tertentu'][$discount->scope] }}</small></td>
          <td>
            @if ($discount->starts_at || $discount->ends_at)
              {{ $discount->starts_at?->format('d/m/Y') ?? '…' }} – {{ $discount->ends_at?->format('d/m/Y') ?? '…' }}
            @else Tanpa batas tanggal @endif
            <small>{{ $discount->days ? collect($discount->days)->map(fn ($d) => $dayNames[$d])->join(', ') : 'Setiap hari' }}</small></td>
          <td class="num">{{ $discount->used_count }}{{ $discount->usage_limit ? ' / ' . $discount->usage_limit : '' }}</td>
          <td>{{ $discount->all_branches ? 'Semua cabang' : $discount->branches->pluck('name')->join(', ') }}</td>
          <td><span class="chip {{ $discount->is_active ? '' : 'chip--off' }}">{{ $discount->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
          <td>
            <div class="disc-actions">
              <a class="btn btn--light btn--small" href="{{ route('admin.discounts.edit', $discount) }}">Ubah</a>
              <form method="post" action="{{ route('admin.discounts.toggle', $discount) }}">@csrf<button class="btn btn--light btn--small" type="submit">{{ $discount->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button></form>
              <form method="post" action="{{ route('admin.discounts.destroy', $discount) }}" data-confirm="Promo yang pernah dipakai hanya dinonaktifkan." data-confirm-title="Hapus promo ini?" data-confirm-label="Hapus" data-confirm-danger">@csrf @method('DELETE')<button class="btn btn--light btn--small" type="submit">Hapus</button></form>
            </div>
          </td>
        </tr>
      @endforeach
      </tbody>
    </table>
    <p class="hint">Satu pesanan hanya memakai satu promo: yang potongannya terbesar. Promo yang sudah pernah dipakai tidak dihapus, hanya dinonaktifkan.</p>
  @endif
</div>
@endsection
