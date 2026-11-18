@php
    $isPending = $order->status === \App\Enums\OrderStatus::PendingPayment;
    $details = $payment->details;
    $code = $details['payment']['payment_code'] ?? '';
@endphp
@extends('layouts.app', ['title' => $brand->name . ' · ' . $order->code, 'brandName' => $brand->name])

@section('bar')
  <span class="chip">Meja {{ $order->table_label }}</span>
@endsection

@section('content')
<main class="wrap stack">
  <p class="label">Pesanan {{ $order->code }}</p>
  <h1 class="display">{{ $order->status->customerHeadline() }}</h1>
  @if ($subline !== '')<p class="muted">{{ $subline }}</p>@endif

  @if ($progress)
    <link rel="stylesheet" href="{{ asset('assets/progress.css') }}?v={{ filemtime(public_path('assets/progress.css')) }}">
    <ol class="progress" aria-label="Progres pesanan">
      @foreach ($progress as $step)
        <li class="progress-step is-{{ $step['state'] }}" @if ($step['state'] === 'current') aria-current="step" @endif><i aria-hidden="true"></i><span>{{ $step['label'] }}</span></li>
      @endforeach
    </ol>
  @endif

  @if ($isPending)
    <p class="pay-amount num">Rp {{ number_format($order->total, 0, ',', '.') }}
      <button class="btn btn--light btn--small" type="button" data-copy="{{ $order->total }}">Salin</button></p>

    @if (\App\Services\PaymentService::requiresDeliveryTerms((int) $order->total))
      <p class="notice" role="note">{{ config('onepay.kasera.delivery_terms_text') }}</p>
    @endif

    @if ($gatewayError || $details === null)
      <p class="notice notice--warn">Tagihan belum bisa dibuat. Pesananmu tersimpan; coba lagi sebentar.</p>
      <button class="btn" type="button" data-reload>Coba lagi</button>
    @else
      <link rel="stylesheet" href="{{ asset('assets/pay.css') }}?v={{ filemtime(public_path('assets/pay.css')) }}">
      @php($bankLogo = collect(['svg', 'png', 'webp'])->first(fn ($ext) => is_file(public_path("assets/banks/{$payment->method}.$ext"))))
      <div class="card stack" id="payCard">
        <div class="row"><span class="pay-method">
            @if ($bankLogo)<img class="bank-logo" src="{{ asset("assets/banks/{$payment->method}.$bankLogo") }}" alt="" height="28">@endif
            <span class="chip">{{ $method['label'] }}</span></span>
          <span class="countdown num" id="countdown" data-expires="{{ $payment->expires_at?->timestamp }}"></span></div>
        <p class="notice notice--warn" id="expiredNotice" role="alert" hidden>Waktu pembayaran habis. Jangan bayar dengan kode ini lagi. Pindai ulang QR di mejamu untuk memesan.</p>

        @if ($method['type'] === \App\Services\Kasera\PaymentMethods::TYPE_QR)
          <div class="qr"><div id="qrBox" data-qr="{{ $details['payment']['qr_string'] ?? '' }}"></div></div>
          <div class="actions">
            <button class="btn btn--light btn--small" type="button" id="qrDownload">Unduh QR</button>
            <button class="btn btn--light btn--small" type="button" data-copy="{{ $details['payment']['qr_string'] ?? '' }}">Salin teks QR</button>
          </div>
          <p class="muted">Buka aplikasi bank atau e-wallet, pilih bayar dengan QRIS, lalu pindai kode ini.</p>
        @else
          <p class="label">Nomor virtual account</p>
          <p class="code num">{{ $code }}</p>
          <button class="btn btn--light btn--small" type="button" data-copy="{{ $code }}">Salin nomor</button>
          <p class="muted">Transfer dengan nominal persis seperti di atas.</p>
        @endif

        @if (!empty($details['instructions']['steps']))
          <p class="label">{{ $details['instructions']['title'] ?? 'Cara bayar' }}</p>
          <ol class="steps">
            @foreach ($details['instructions']['steps'] as $step)
              <li>{{ is_array($step) ? ($step['text'] ?? '') : $step }}</li>
            @endforeach
          </ol>
        @endif
      </div>
    @endif
  @endif

  <div class="card stack">
    @foreach ($order->items as $line)
      <div class="row"><span>{{ $line->qty }}× {{ $line->name }}</span><span class="num">Rp {{ number_format($line->unit_price * $line->qty, 0, ',', '.') }}</span></div>
    @endforeach
    @if ($order->discount > 0)
      <div class="row"><span class="muted">Diskon{{ $order->discount_label ? ' · ' . $order->discount_label : '' }}</span><span class="num">− Rp {{ number_format($order->discount, 0, ',', '.') }}</span></div>
    @endif
    <div class="row"><b>Total</b><b class="num">Rp {{ number_format($order->total, 0, ',', '.') }}</b></div>
  </div>
</main>
@endsection

@push('scripts')
<script nonce="{{ $cspNonce ?? '' }}">window.PAY = @json(['status' => $order->status->value, 'endpoint' => route('order.status', $order)]);</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" defer></script>
<script src="{{ asset('assets/pay.js') }}?v={{ filemtime(public_path('assets/pay.js')) }}" defer></script>
@endpush
