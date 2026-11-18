{{-- Isi laporan: dipakai halaman utama dan halaman cetak. Butuh $report dan $filter. --}}
@php
    use App\Services\Reports\ReportCsv;

    $rp = fn ($n) => 'Rp ' . number_format((int) $n, 0, ',', '.');
    $pct = fn ($v) => ReportCsv::percent($v === null ? null : (float) $v);
    $s = $report['summary'];
    $maxHour = max(1, max(array_column($report['by_hour'], 'revenue')));
    $maxDay = max(1, max(array_column($report['by_day'], 'revenue')));
    $maxCategory = max(1, max([1, ...array_column($report['by_category'], 'revenue')]));
    $hasSales = $s['sold_orders'] > 0;
    $manyDays = count($report['by_day']) > 31;
@endphp

@unless ($hasSales)
  <p class="notice">Belum ada pesanan terbayar pada rentang ini.</p>
@endunless

{{-- Ringkasan --}}
<section class="rp-kpis" aria-label="Ringkasan">
  <div class="card rp-hero"><p class="label">Omzet bersih</p><p class="rp-big num">{{ $rp($s['net']) }}</p><p class="muted">Bruto {{ $rp($s['gross']) }} dikurangi refund {{ $rp($s['refunds']) }}</p></div>
  <div class="card"><p class="label">Pesanan terjual</p><p class="rp-num num">{{ number_format($s['sold_orders'], 0, ',', '.') }}</p></div>
  <div class="card"><p class="label">Rata-rata per pesanan</p><p class="rp-num num">{{ $rp($s['average_order']) }}</p></div>
  <div class="card"><p class="label">Total diskon</p><p class="rp-num num">{{ $rp($s['discount']) }}</p></div>
  <div class="card"><p class="label">Dibatalkan</p><p class="rp-num num">{{ $s['cancelled_orders'] }} <small>{{ $pct($s['cancel_rate']) }}</small></p></div>
  <div class="card"><p class="label">Refund</p><p class="rp-num num">{{ $rp($s['refunds']) }} <small>{{ $pct($s['refund_rate']) }}</small></p></div>
</section>

{{-- Per jam --}}
<section class="rp-section">
  <h2 class="section">Penjualan per jam</h2>
  <p class="muted">@if ($report['peak_hour'] !== null)Jam tersibuk {{ sprintf('%02d:00', $report['peak_hour']) }}, omzet tertinggi {{ $rp($report['by_hour'][$report['peak_hour']]['revenue']) }}.@else Belum ada data.@endif</p>
  <div class="card">
    <div class="rp-chart rp-chart--hours" role="img" aria-label="Omzet per jam. Rincian ada pada tabel di bawah.">
      @foreach ($report['by_hour'] as $row)
        <div class="rp-col {{ $row['hour'] === $report['peak_hour'] ? 'is-peak' : '' }}" data-tip="{{ sprintf('%02d:00', $row['hour']) }} · {{ $row['orders'] }} pesanan · {{ $rp($row['revenue']) }}" tabindex="0">
          <i style="--h: {{ round($row['revenue'] / $maxHour * 100, 1) }}%"></i>
          <span>{{ $row['hour'] % 3 === 0 ? sprintf('%02d', $row['hour']) : '' }}</span>
        </div>
      @endforeach
    </div>
    <details class="rp-table-toggle"><summary>Tampilkan tabel</summary>
      <table class="rp-table"><thead><tr><th>Jam</th><th class="r">Pesanan</th><th class="r">Omzet</th></tr></thead><tbody>
        @foreach ($report['by_hour'] as $row)@if ($row['orders'] > 0)<tr><td>{{ sprintf('%02d:00', $row['hour']) }}</td><td class="r num">{{ $row['orders'] }}</td><td class="r num">{{ $rp($row['revenue']) }}</td></tr>@endif @endforeach
      </tbody></table>
    </details>
  </div>
</section>

{{-- Per hari --}}
<section class="rp-section">
  <h2 class="section">Penjualan per hari</h2>
  <div class="card">
    <div class="rp-chart rp-chart--days {{ $manyDays ? 'is-dense' : '' }}" role="img" aria-label="Omzet per hari. Rincian ada pada tabel di bawah.">
      @foreach ($report['by_day'] as $i => $row)
        <div class="rp-col" data-tip="{{ \Carbon\Carbon::parse($row['date'])->translatedFormat('D, d M Y') }} · {{ $row['orders'] }} pesanan · {{ $rp($row['revenue']) }}" tabindex="0">
          <i style="--h: {{ round($row['revenue'] / $maxDay * 100, 1) }}%"></i>
          <span>{{ (!$manyDays || $i % 7 === 0) ? \Carbon\Carbon::parse($row['date'])->format('d/m') : '' }}</span>
        </div>
      @endforeach
    </div>
    <details class="rp-table-toggle"><summary>Tampilkan tabel</summary>
      <table class="rp-table"><thead><tr><th>Tanggal</th><th class="r">Pesanan</th><th class="r">Omzet</th></tr></thead><tbody>
        @foreach ($report['by_day'] as $row)<tr><td>{{ \Carbon\Carbon::parse($row['date'])->translatedFormat('D, d M Y') }}</td><td class="r num">{{ $row['orders'] }}</td><td class="r num">{{ $rp($row['revenue']) }}</td></tr>@endforeach
      </tbody></table>
    </details>
  </div>
</section>

{{-- Kategori --}}
<section class="rp-section">
  <h2 class="section">Penjualan per kategori</h2>
  <div class="card">
    <table class="rp-table"><thead><tr><th>Kategori</th><th class="r">Terjual</th><th class="r">Omzet</th><th class="rp-bar-col"><span class="sr">Proporsi</span></th></tr></thead><tbody>
      @forelse ($report['by_category'] as $row)
        <tr><td>{{ $row['name'] }}</td><td class="r num">{{ $row['qty'] }}</td><td class="r num">{{ $rp($row['revenue']) }}</td>
          <td class="rp-bar-col"><span class="rp-bar" style="--w: {{ round($row['revenue'] / $maxCategory * 100, 1) }}%"></span></td></tr>
      @empty<tr><td colspan="4" class="muted">Belum ada data.</td></tr>@endforelse
    </tbody></table>
  </div>
</section>

{{-- Menu --}}
<section class="rp-section rp-two">
  @foreach ([['Menu terlaris', $report['top_items']], ['Menu kurang laku', $report['low_items']]] as [$title, $items])
    <div>
      <h2 class="section">{{ $title }}</h2>
      <div class="card">
        <table class="rp-table"><thead><tr><th>Menu</th><th class="r">Terjual</th><th class="r">Omzet</th><th class="r">Margin</th></tr></thead><tbody>
          @forelse ($items as $row)
            <tr><td>{{ $row['name'] }}</td><td class="r num">{{ $row['qty'] }}</td><td class="r num">{{ $rp($row['revenue']) }}</td>
              <td class="r num">{{ $rp($row['margin']) }} <small>{{ $row['qty'] > 0 ? $pct($row['margin_rate']) : '' }}</small></td></tr>
          @empty<tr><td colspan="4" class="muted">Belum ada data.</td></tr>@endforelse
        </tbody></table>
      </div>
    </div>
  @endforeach
</section>

{{-- Metode bayar --}}
<section class="rp-section">
  <h2 class="section">Metode pembayaran</h2>
  <div class="card">
    <table class="rp-table"><thead><tr><th>Metode</th><th class="r">Pesanan</th><th class="r">Omzet</th></tr></thead><tbody>
      @forelse ($report['by_method'] as $row)<tr><td>{{ ReportCsv::methodLabel($row['method']) }}</td><td class="r num">{{ $row['orders'] }}</td><td class="r num">{{ $rp($row['revenue']) }}</td></tr>
      @empty<tr><td colspan="3" class="muted">Belum ada data.</td></tr>@endforelse
    </tbody></table>
  </div>
</section>

{{-- Meja, area, konversi --}}
<section class="rp-section">
  <h2 class="section">Meja, area, dan konversi scan QR</h2>
  <div class="rp-funnel">
    <div class="card"><p class="label">Scan QR</p><p class="rp-num num">{{ number_format($report['funnel']['scans'], 0, ',', '.') }}</p></div>
    <div class="card"><p class="label">Pesanan dibuat</p><p class="rp-num num">{{ number_format($report['funnel']['created'], 0, ',', '.') }}</p></div>
    <div class="card"><p class="label">Pesanan terbayar</p><p class="rp-num num">{{ number_format($report['funnel']['paid'], 0, ',', '.') }}</p></div>
    <div class="card"><p class="label">Scan ke terbayar</p><p class="rp-num num">{{ $pct($report['funnel']['scan_to_paid']) }}</p></div>
  </div>
  <div class="rp-two">
    <div class="card">
      <table class="rp-table"><caption class="label">Per area</caption><thead><tr><th>Area</th><th class="r">Pesanan</th><th class="r">Omzet</th><th class="r">Scan</th><th class="r">Konversi</th></tr></thead><tbody>
        @forelse ($report['areas'] as $row)<tr><td>{{ $row['area'] }}</td><td class="r num">{{ $row['orders'] }}</td><td class="r num">{{ $rp($row['revenue']) }}</td><td class="r num">{{ $row['scans'] }}</td><td class="r num">{{ $pct($row['conversion']) }}</td></tr>
        @empty<tr><td colspan="5" class="muted">Belum ada data.</td></tr>@endforelse
      </tbody></table>
    </div>
    <div class="card">
      <table class="rp-table"><caption class="label">Per meja</caption><thead><tr><th>Meja</th><th class="r">Pesanan</th><th class="r">Omzet</th><th class="r">Scan</th><th class="r">Konversi</th></tr></thead><tbody>
        @forelse ($report['tables'] as $row)<tr><td>{{ $row['label'] }} <small class="muted">{{ $row['area'] }}</small></td><td class="r num">{{ $row['orders'] }}</td><td class="r num">{{ $rp($row['revenue']) }}</td><td class="r num">{{ $row['scans'] }}</td><td class="r num">{{ $pct($row['conversion']) }}</td></tr>
        @empty<tr><td colspan="5" class="muted">Belum ada data.</td></tr>@endforelse
      </tbody></table>
    </div>
  </div>
</section>

{{-- Pajak dan service --}}
<section class="rp-section">
  <h2 class="section">Rekap pajak dan service charge</h2>
  <div class="card">
    <table class="rp-table"><tbody>
      <tr><td>Subtotal menu</td><td class="r num">{{ $rp($s['subtotal']) }}</td></tr>
      <tr><td>Diskon</td><td class="r num">− {{ $rp($s['discount']) }}</td></tr>
      <tr><td>Service charge</td><td class="r num">{{ $rp($s['service']) }}</td></tr>
      <tr><td>Pajak</td><td class="r num">{{ $rp($s['tax']) }}</td></tr>
      <tr><td>Pembulatan</td><td class="r num">{{ $rp($s['rounding']) }}</td></tr>
    </tbody></table>
  </div>
</section>

{{-- Perbandingan cabang --}}
@if ($report['branches'] !== [])
<section class="rp-section">
  <h2 class="section">Perbandingan antar cabang</h2>
  <div class="card">
    <table class="rp-table"><thead><tr><th>Cabang</th><th class="r">Pesanan</th><th class="r">Omzet bruto</th><th class="r">Refund</th><th class="r">Omzet bersih</th><th class="r">Rata-rata</th><th class="r">Batal</th></tr></thead><tbody>
      @foreach ($report['branches'] as $row)
        <tr><td>{{ $row['branch'] }}</td><td class="r num">{{ $row['sold_orders'] }}</td><td class="r num">{{ $rp($row['gross']) }}</td><td class="r num">{{ $rp($row['refunds']) }}</td><td class="r num">{{ $rp($row['net']) }}</td><td class="r num">{{ $rp($row['average_order']) }}</td><td class="r num">{{ $pct($row['cancel_rate']) }}</td></tr>
      @endforeach
    </tbody></table>
  </div>
</section>
@endif
