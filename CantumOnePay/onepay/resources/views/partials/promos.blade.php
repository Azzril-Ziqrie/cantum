{{-- Promo otomatis yang sedang berlaku di cabang. --}}
@if (!empty($promos))
  <link rel="stylesheet" href="{{ asset('assets/promo.css') }}?v={{ filemtime(public_path('assets/promo.css')) }}">
  <section class="promos" aria-label="Promo hari ini">
    @foreach ($promos as $promo)
      <p class="promo"><span class="tag">Promo</span><b>{{ $promo['name'] }}</b><span class="muted">{{ $promo['summary'] }}</span></p>
    @endforeach
  </section>
@endif
