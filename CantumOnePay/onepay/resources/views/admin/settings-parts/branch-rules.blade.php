@php
    $chosenMethods = $branch?->enabled_methods !== null ? array_map('trim', explode(',', $branch->enabled_methods)) : [];
    $restrict = old('restrict_methods', $branch?->enabled_methods !== null);
    $roundingLabels = [1 => 'Tanpa pembulatan', 100 => 'Ke Rp 100', 500 => 'Ke Rp 500', 1000 => 'Ke Rp 1.000'];
@endphp
<fieldset class="stack adm-rules">
  <legend class="label">Aturan khusus cabang</legend>
  <p class="muted">Kosong = ikut brand (pajak {{ $brand->tax_percent + 0 }}%, service {{ $brand->service_percent + 0 }}%). Pesanan yang sudah dibuat tidak berubah.</p>
  <div class="adm-grid">
    <label class="adm-field"><span class="label">Pajak (%)</span><input type="number" name="tax_percent" value="{{ old('tax_percent', $branch?->tax_percent !== null ? $branch->tax_percent + 0 : '') }}" step="0.01" min="0" max="100" placeholder="ikut brand"></label>
    <label class="adm-field"><span class="label">Service charge (%)</span><input type="number" name="service_percent" value="{{ old('service_percent', $branch?->service_percent !== null ? $branch->service_percent + 0 : '') }}" step="0.01" min="0" max="100" placeholder="ikut brand"></label>
    <label class="adm-field"><span class="label">Pembulatan total</span>
      <select name="rounding_to">
        <option value="">Ikut brand</option>
        @foreach ($roundingLabels as $step => $label)
          <option value="{{ $step }}" @selected((string) old('rounding_to', $branch?->rounding_to) === (string) $step)>{{ $label }}</option>
        @endforeach
      </select>
    </label>
  </div>
  <label class="adm-check"><input type="checkbox" name="restrict_methods" value="1" @checked($restrict)>Batasi metode pembayaran di cabang ini (kosong = ikut brand)</label>
  <div class="adm-grid">
    @foreach ($methods as $code => $method)
      @continue(!in_array($code, $enabledMethods, true))
      <label class="adm-check"><input type="checkbox" name="branch_methods[]" value="{{ $code }}" @checked(in_array($code, (array) old('branch_methods', $chosenMethods), true))>{{ $method['label'] }}</label>
    @endforeach
  </div>
</fieldset>
