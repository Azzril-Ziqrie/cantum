@extends('layouts.staff', ['title' => 'Pengguna'])

@section('content')
<link rel="stylesheet" href="{{ asset('assets/admin-settings.css') }}?v={{ filemtime(public_path('assets/admin-settings.css')) }}">
<div class="content adm">
  @include('admin.settings-parts.flash')

  <details class="card adm-user">
    <summary><h2>Tambah pengguna</h2><span class="chip">Baru</span></summary>
    <form method="post" action="{{ route('admin.users.store') }}" class="stack">
      @csrf
      @include('admin.settings-parts.user-fields', ['user' => null])
      <label class="adm-field"><span class="label">Kata sandi (min. 8 karakter)</span><input type="password" name="password" required minlength="8" autocomplete="new-password"></label>
      <button class="btn" type="submit">Tambah pengguna</button>
    </form>
  </details>

  @foreach ($users as $user)
    <details class="card adm-user {{ $user->is_active ? '' : 'adm-off' }}">
      <summary>
        <div><b>{{ $user->name }}</b><div class="muted">{{ $user->email }}</div></div>
        <div class="adm-meta">
          <span class="chip">{{ $roleLabels[$user->role->value] }}</span>
          @unless ($user->is_active)<span class="chip">Nonaktif</span>@endunless
        </div>
      </summary>

      <form method="post" action="{{ route('admin.users.update', $user) }}" class="stack">
        @csrf @method('PUT')
        @include('admin.settings-parts.user-fields', ['user' => $user])
        <label class="adm-field"><span class="label">Kata sandi baru (kosongkan bila tidak diganti)</span><input type="password" name="password" minlength="8" autocomplete="new-password"></label>
        <button class="btn btn--small" type="submit">Simpan</button>
      </form>

      <form method="post" action="{{ route('admin.users.toggle', $user) }}">
        @csrf
        <button class="btn btn--light btn--small" type="submit">{{ $user->is_active ? 'Nonaktifkan' : 'Aktifkan kembali' }}</button>
        <span class="muted">Riwayat pengguna tidak dihapus.</span>
      </form>
    </details>
  @endforeach
</div>
@endsection
