@extends('layouts.staff', ['title' => 'Pengaturan'])

@section('content')
<div class="content adm">
  @include('admin.settings-parts.subnav')
  @include('admin.settings-parts.flash')
  @include('admin.settings-parts.health')

  {{-- Brand --}}
  <form method="post" action="{{ route('admin.settings.brand') }}" class="card adm-section" id="brand">
    @csrf @method('PUT')
    <h2>Brand dan pembayaran</h2>
    <div class="adm-grid adm-grid--wide">
      <label class="adm-field"><span class="label">Nama resto</span><input type="text" name="name" value="{{ old('name', $brand->name) }}" required maxlength="120"></label>
      <label class="adm-field"><span class="label">Slogan halaman pelanggan</span><input type="text" name="tagline" value="{{ old('tagline', $brand->tagline) }}" maxlength="160"></label>
      <label class="adm-field"><span class="label">Warna aksen</span><input type="color" name="accent_color" value="{{ old('accent_color', $brand->accent_color) }}"></label>
    </div>
    <div class="adm-grid">
      <label class="adm-field"><span class="label">Pajak (%)</span><input type="number" name="tax_percent" value="{{ old('tax_percent', $brand->tax_percent + 0) }}" step="0.01" min="0" max="100" required></label>
      <label class="adm-field"><span class="label">Service charge (%)</span><input type="number" name="service_percent" value="{{ old('service_percent', $brand->service_percent + 0) }}" step="0.01" min="0" max="100" required></label>
      <label class="adm-field"><span class="label">Pembulatan total</span>
        <select name="rounding_to">
          @foreach ([1 => 'Tanpa pembulatan', 100 => 'Ke Rp 100', 500 => 'Ke Rp 500', 1000 => 'Ke Rp 1.000'] as $step => $label)
            <option value="{{ $step }}" @selected((int) old('rounding_to', $brand->rounding_to) === $step)>{{ $label }}</option>
          @endforeach
        </select>
      </label>
    </div>
    <fieldset class="stack" style="border:0">
      <legend class="label">Metode pembayaran aktif</legend>
      <div class="adm-grid">
        @foreach ($methods as $code => $method)
          <label class="adm-check"><input type="checkbox" name="enabled_methods[]" value="{{ $code }}" @checked(in_array($code, (array) old('enabled_methods', $enabledMethods)))>{{ $method['label'] }}</label>
        @endforeach
      </div>
    </fieldset>
    <div><button class="btn" type="submit">Simpan brand</button></div>
  </form>

  {{-- Cabang --}}
  <section class="adm-section" id="cabang">
    <h2>Cabang</h2>
    @foreach ($branches as $branch)
      @include('admin.settings-parts.branch-form', ['branch' => $branch])
    @endforeach
    @include('admin.settings-parts.branch-form', ['branch' => null])
  </section>

  {{-- Gateway --}}
  <section class="card adm-section" id="gateway">
    <div class="row"><h2>Gateway Kasera Pay</h2><span class="chip">{{ $gateway['mode'] }}</span></div>
    @if ($gateway['warning'])<p class="notice notice--warn" role="alert">{{ $gateway['warning'] }}</p>@endif
    <p class="muted">Kunci milik resto sendiri. Disimpan terenkripsi di database dan menimpa nilai di <code>.env</code>. Kunci tidak pernah ditampilkan utuh.</p>
    <div class="adm-grid adm-grid--wide">
      <div><p class="label">Kunci API</p><p class="adm-secret num">{{ $gateway['api_key'] ?: 'belum diatur' }}</p></div>
      <div><p class="label">Webhook secret</p><p class="adm-secret num">{{ $gateway['webhook_secret'] ?: 'belum diatur' }}</p></div>
      <div><p class="label">Status</p><p>{{ $gateway['configured'] ? 'Siap dipakai' : 'Belum lengkap' }}</p></div>
    </div>
    <div><p class="label">URL webhook (daftarkan di Kasera: Developer &gt; Webhooks)</p><p class="adm-url num">{{ $gateway['webhook_url'] }}</p></div>

    <form method="post" action="{{ route('admin.settings.gateway') }}" class="stack">
      @csrf @method('PUT')
      <div class="adm-grid adm-grid--wide">
        <label class="adm-field"><span class="label">Kunci API baru (kp_test_… atau kp_live_…)</span><input type="password" name="api_key" autocomplete="off" placeholder="Kosongkan bila tidak diganti"></label>
        <label class="adm-field"><span class="label">Webhook secret baru</span><input type="password" name="webhook_secret" autocomplete="off" placeholder="Kosongkan bila tidak diganti"></label>
      </div>
      <div class="actions"><button class="btn" type="submit">Simpan kunci</button></div>
    </form>
    <form method="post" action="{{ route('admin.settings.gateway.test') }}">
      @csrf
      <button class="btn btn--light" type="submit">Uji koneksi ke Kasera</button>
    </form>
  </section>

  {{-- Backup dan ekspor --}}
  <section class="card adm-section" id="backup">
    <h2>Cadangan dan ekspor data</h2>
    <p class="muted">Backup terakhir: {{ $brand->last_backup_at ? $brand->last_backup_at->translatedFormat('d M Y H:i') . ($brand->backup_note ? ' · ' . $brand->backup_note : '') : 'belum pernah dicatat' }}</p>
    <div class="actions">
      <a class="btn btn--light btn--small" href="{{ route('admin.settings.export.menu') }}">Ekspor menu (CSV)</a>
      <a class="btn btn--light btn--small" href="{{ route('admin.settings.export.orders') }}">Ekspor pesanan 30 hari (CSV)</a>
    </div>
    <form method="post" action="{{ route('admin.settings.backup') }}" class="adm-grid adm-grid--wide">
      @csrf
      <label class="adm-field"><span class="label">Catatan backup database</span><input type="text" name="note" maxlength="200" placeholder="mis. dump manual ke drive"></label>
      <div style="align-self:end"><button class="btn btn--small" type="submit">Catat backup sekarang</button></div>
    </form>
  </section>
</div>
@endsection
