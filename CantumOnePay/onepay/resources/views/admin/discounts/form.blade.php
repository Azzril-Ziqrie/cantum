@php
    $editing = $discount->exists;
    $dayNames = [1 => 'Sen', 2 => 'Sel', 3 => 'Rab', 4 => 'Kam', 5 => 'Jum', 6 => 'Sab', 7 => 'Min'];
    $scope = old('scope', $discount->scope);
    $selectedTargets = collect(old('target_ids', $discount->exists ? $discount->targets->pluck('target_id')->all() : []))->map(fn ($id) => (int) $id);
    $selectedBranches = collect(old('branch_ids', $discount->exists ? $discount->branches->pluck('id')->all() : []))->map(fn ($id) => (int) $id);
    $selectedDays = collect(old('days', $discount->days ?? []))->map(fn ($d) => (int) $d);
    $allBranches = (bool) old('all_branches', $discount->all_branches);
    $field = fn (string $key, $default = null) => old($key, $default);
    $time = fn (?string $value) => $value ? substr($value, 0, 5) : '';
@endphp
@extends('layouts.staff', ['title' => $editing ? 'Ubah promo' : 'Promo baru'])

@section('top')
  <a class="btn btn--light btn--small" href="{{ route('admin.discounts') }}">Kembali</a>
@endsection

@section('content')
<link rel="stylesheet" href="{{ asset('assets/discounts.css') }}?v={{ filemtime(public_path('assets/discounts.css')) }}">
<div class="content">
  @if ($errors->any())
    <p class="notice notice--warn" role="alert">Ada isian yang perlu diperbaiki: {{ $errors->all()[0] }}</p>
  @endif

  <form class="disc-form" method="post" action="{{ $editing ? route('admin.discounts.update', $discount) : route('admin.discounts.store') }}">
    @csrf
    @if ($editing) @method('PUT') @endif

    <fieldset>
      <legend>Promo</legend>
      <label class="field"><span class="label">Nama promo</span><input name="name" value="{{ $field('name', $discount->name) }}" maxlength="120" required placeholder="Happy hour kopi"></label>
      <div>
        <label class="field"><span class="label">Kode promo (kosongkan untuk promo otomatis)</span><input name="code" value="{{ $field('code', $discount->code) }}" maxlength="40" placeholder="HEMAT10" style="text-transform:uppercase"></label>
        <p class="hint">Promo otomatis langsung berlaku bila syaratnya terpenuhi. Promo berkode hanya berlaku bila pelanggan mengetik kodenya.</p>
      </div>
      <label class="check"><input type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', $discount->is_active))> Aktif</label>
    </fieldset>

    <fieldset>
      <legend>Potongan</legend>
      <div class="pills" role="radiogroup" aria-label="Tipe potongan">
        <label><input type="radio" name="type" value="percent" @checked($field('type', $discount->type) === 'percent') data-type><span>Persen</span></label>
        <label><input type="radio" name="type" value="fixed" @checked($field('type', $discount->type) === 'fixed') data-type><span>Nominal tetap</span></label>
      </div>
      <div class="grid-2">
        <label class="field"><span class="label" id="valueLabel">Nilai</span><input type="number" name="value" min="1" value="{{ $field('value', $discount->value) }}" required inputmode="numeric"></label>
        <label class="field" data-when="percent"><span class="label">Plafon potongan (Rp, opsional)</span><input type="number" name="max_discount" min="1" value="{{ $field('max_discount', $discount->max_discount) }}" inputmode="numeric"></label>
      </div>
      <label class="field"><span class="label">Berlaku untuk</span>
        <select name="scope" id="scope" style="width:100%;height:48px;border:0;border-radius:14px;padding:0 16px;background:#fff;box-shadow:0 0 0 1px var(--line) inset">
          <option value="order" @selected($scope === 'order')>Seluruh pesanan</option>
          <option value="items" @selected($scope === 'items')>Menu tertentu</option>
          <option value="categories" @selected($scope === 'categories')>Kategori tertentu</option>
        </select></label>
      <div data-scope="items" class="target-list" role="group" aria-label="Menu yang dapat potongan">
        @foreach ($items as $item)
          <label><input type="checkbox" name="target_ids[]" value="{{ $item->id }}" @checked($scope === 'items' && $selectedTargets->contains($item->id)) data-target-items>{{ $item->name }} <span class="muted">· {{ $item->category?->name }}</span></label>
        @endforeach
      </div>
      <div data-scope="categories" class="target-list" role="group" aria-label="Kategori yang dapat potongan">
        @foreach ($categories as $category)
          <label><input type="checkbox" name="target_ids[]" value="{{ $category->id }}" @checked($scope === 'categories' && $selectedTargets->contains($category->id)) data-target-categories>{{ $category->name }}</label>
        @endforeach
      </div>
      <label class="field"><span class="label">Minimal belanja (Rp)</span><input type="number" name="min_spend" min="0" value="{{ $field('min_spend', $discount->min_spend ?: 0) }}" inputmode="numeric"></label>
    </fieldset>

    <fieldset>
      <legend>Kapan berlaku</legend>
      <div class="grid-2">
        <label class="field"><span class="label">Mulai tanggal</span><input type="date" name="starts_at" value="{{ $field('starts_at', $discount->starts_at?->toDateString()) }}"></label>
        <label class="field"><span class="label">Sampai tanggal</span><input type="date" name="ends_at" value="{{ $field('ends_at', $discount->ends_at?->toDateString()) }}"></label>
      </div>
      <div>
        <span class="label">Hari (kosong = setiap hari)</span>
        <div class="pills" role="group" aria-label="Hari berlaku">
          @foreach ($dayNames as $number => $name)
            <label><input type="checkbox" name="days[]" value="{{ $number }}" @checked($selectedDays->contains($number))><span>{{ $name }}</span></label>
          @endforeach
        </div>
      </div>
      <div class="grid-2">
        <label class="field"><span class="label">Happy hour: dari jam</span><input type="time" name="time_from" value="{{ $field('time_from', $time($discount->time_from)) }}"></label>
        <label class="field"><span class="label">sampai jam (tidak termasuk)</span><input type="time" name="time_until" value="{{ $field('time_until', $time($discount->time_until)) }}"></label>
      </div>
      <label class="field"><span class="label">Batas pemakaian total (kosong = tanpa batas)</span><input type="number" name="usage_limit" min="1" value="{{ $field('usage_limit', $discount->usage_limit) }}" inputmode="numeric"></label>
      @if ($editing)<p class="hint">Sudah dipakai {{ $discount->used_count }} kali.</p>@endif
    </fieldset>

    <fieldset>
      <legend>Cabang</legend>
      @if ($isOwner)
        <label class="check"><input type="checkbox" name="all_branches" value="1" id="allBranches" @checked($allBranches)> Semua cabang</label>
      @endif
      <div class="pills" id="branchPills" role="group" aria-label="Cabang">
        @foreach ($branches as $branch)
          <label><input type="checkbox" name="branch_ids[]" value="{{ $branch->id }}" @checked($selectedBranches->contains($branch->id))><span>{{ $branch->name }}</span></label>
        @endforeach
      </div>
      @unless ($isOwner)<p class="hint">Sebagai manager kamu hanya bisa membuat promo untuk cabangmu.</p>@endunless
    </fieldset>

    <div class="row"><span></span><button class="btn" type="submit">{{ $editing ? 'Simpan perubahan' : 'Buat promo' }}</button></div>
  </form>
</div>
@endsection

@push('scripts')
<script nonce="{{ $cspNonce ?? '' }}">
(function () {
  var scope = document.getElementById('scope');
  var typeRadios = document.querySelectorAll('[data-type]');
  var allBranches = document.getElementById('allBranches');

  function sync() {
    var current = scope.value;
    document.querySelectorAll('[data-scope]').forEach(function (box) {
      var active = box.dataset.scope === current;
      box.hidden = !active;
      box.querySelectorAll('input').forEach(function (input) { input.disabled = !active; });
    });
    var isPercent = document.querySelector('[data-type]:checked').value === 'percent';
    document.querySelectorAll('[data-when="percent"]').forEach(function (node) { node.hidden = !isPercent; });
    document.getElementById('valueLabel').textContent = isPercent ? 'Nilai (persen, 1 sampai 100)' : 'Potongan (Rp)';
    var pills = document.getElementById('branchPills');
    if (allBranches) pills.hidden = allBranches.checked;
  }

  scope.addEventListener('change', sync);
  typeRadios.forEach(function (radio) { radio.addEventListener('change', sync); });
  if (allBranches) allBranches.addEventListener('change', sync);
  sync();
})();
</script>
@endpush
