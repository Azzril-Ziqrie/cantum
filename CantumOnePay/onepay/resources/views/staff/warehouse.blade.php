@extends('layouts.staff', ['title' => 'Gudang'])

@section('content')
<div class="content">
  <div class="card stack" style="max-width:640px">
    <span class="chip">Segera hadir</span>
    <h2 class="section" style="margin:0">Modul stok akan tersedia di pembaruan berikutnya.</h2>
    <p class="muted">Penerimaan barang, stok bahan per cabang, opname, dan catatan waste akan muncul di sini. Sampai saat itu, tidak ada yang perlu kamu kerjakan di aplikasi ini.</p>
    <dl class="stack">
      <div class="row"><dt class="label">Akun</dt><dd>{{ $user->name }}</dd></div>
      <div class="row"><dt class="label">Peran</dt><dd>Gudang</dd></div>
      <div class="row"><dt class="label">Cabang</dt><dd>{{ $branches->isEmpty() ? 'Belum ditugaskan' : $branches->pluck('name')->join(', ') }}</dd></div>
    </dl>
    <form method="post" action="{{ route('staff.logout') }}">@csrf<button class="btn btn--light" type="submit">Keluar</button></form>
  </div>
</div>
@endsection
