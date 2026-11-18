@php
    $failures = collect($health)->where('status', 'fail')->count();
    $warnings = collect($health)->where('status', 'warn')->count();
    $statusLabel = ['ok' => 'OK', 'warn' => 'Peringatan', 'fail' => 'Gagal'];
@endphp
<link rel="stylesheet" href="{{ asset('assets/admin-health.css') }}?v={{ filemtime(public_path('assets/admin-health.css')) }}">
<section class="card adm-section health" id="kesehatan" aria-labelledby="health-title">
  <div class="row">
    <h2 id="health-title">Kesehatan sistem</h2>
    <span class="chip health-summary health-summary--{{ $failures ? 'fail' : ($warnings ? 'warn' : 'ok') }}">
      {{ $failures ? $failures . ' perlu diperbaiki' : ($warnings ? $warnings . ' peringatan' : 'Semua baik') }}
    </span>
  </div>
  <ul class="health-list">
    @foreach ($health as $check)
      <li class="health-item health-item--{{ $check->status }}">
        <span class="health-badge" aria-label="{{ $statusLabel[$check->status] }}">{{ $statusLabel[$check->status] }}</span>
        <div>
          <b>{{ $check->label }}</b>
          <p>{{ $check->message }}</p>
          @if ($check->advice !== '')<p class="health-advice">{{ $check->advice }}</p>@endif
        </div>
      </li>
    @endforeach
  </ul>
  <p class="muted">Pemeriksaan yang sama bisa dijalankan dari terminal: <code>php artisan onepay:doctor</code></p>
</section>
