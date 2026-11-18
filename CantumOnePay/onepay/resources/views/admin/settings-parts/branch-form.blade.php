@php
    $isNew = $branch === null;
    $hours = $branch?->open_hours;
    $useHours = old('use_hours', $hours !== null);
    $holidays = old('holidays', implode("\n", (array) $branch?->holidays));
@endphp
<details class="card adm-branch {{ !$isNew && !$branch->is_active ? 'adm-off' : '' }}">
  <summary>
    @if ($isNew)
      <h3>Tambah cabang</h3><span class="chip">Baru</span>
    @else
      <div><b>{{ $branch->name }}</b><div class="muted">{{ $branch->address }}</div></div>
      <div class="adm-meta"><span class="chip">{{ $branch->is_open ? 'Buka' : 'Tutup' }}</span>@unless ($branch->is_active)<span class="chip">Nonaktif</span>@endunless</div>
    @endif
  </summary>

  <form method="post" action="{{ $isNew ? route('admin.settings.branches.store') : route('admin.settings.branches.update', $branch) }}" class="stack">
    @csrf
    @unless ($isNew) @method('PUT') @endunless
    <div class="adm-grid adm-grid--wide">
      <label class="adm-field"><span class="label">Nama cabang</span><input type="text" name="name" value="{{ $branch->name ?? '' }}" required maxlength="120"></label>
      <label class="adm-field"><span class="label">Alamat</span><input type="text" name="address" value="{{ $branch->address ?? '' }}" maxlength="255"></label>
    </div>
    <div class="adm-grid">
      <label class="adm-field"><span class="label">Estimasi penyajian (menit)</span><input type="number" name="prep_minutes" value="{{ $branch->prep_minutes ?? 15 }}" min="0" max="240" required></label>
      <label class="adm-field"><span class="label">Tutup pesanan N menit sebelum tutup</span><input type="number" name="last_order_minutes" value="{{ $branch->last_order_minutes ?? 0 }}" min="0" max="240" required></label>
      <label class="adm-check"><input type="checkbox" name="is_open" value="1" @checked($branch->is_open ?? true)>Buka sekarang</label>
      <label class="adm-check"><input type="checkbox" name="is_active" value="1" @checked($branch->is_active ?? true)>Cabang aktif</label>
    </div>

    <label class="adm-check"><input type="checkbox" name="use_hours" value="1" @checked($useHours)>Atur jam buka per hari (kosong = buka sepanjang hari)</label>
    <div class="adm-hours">
      @foreach ($dayLabels as $day => $label)
        @php($row = $hours[$day] ?? ['closed' => false, 'open' => '08:00', 'close' => '22:00'])
        <b>{{ $label }}</b>
        <label class="adm-check"><input type="checkbox" name="hours[{{ $day }}][closed]" value="1" @checked($row['closed'])>Libur</label>
        <label class="adm-field"><input type="time" name="hours[{{ $day }}][open]" value="{{ $row['open'] }}" aria-label="Buka {{ $label }}"></label>
        <label class="adm-field"><input type="time" name="hours[{{ $day }}][close]" value="{{ $row['close'] }}" aria-label="Tutup {{ $label }}"></label>
      @endforeach
    </div>

    <label class="adm-field"><span class="label">Hari libur (satu tanggal YYYY-MM-DD per baris)</span><textarea name="holidays">{{ $holidays }}</textarea></label>
    @include('admin.settings-parts.branch-rules')
    <div><button class="btn btn--small" type="submit">{{ $isNew ? 'Tambah cabang' : 'Simpan cabang' }}</button></div>
  </form>
</details>
