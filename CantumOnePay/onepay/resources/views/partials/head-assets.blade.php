{{-- CSS bersama + aksen brand (nilai divalidasi di Brand::accent, aman untuk CSS). --}}
<link rel="stylesheet" href="{{ asset('assets/app.css') }}?v={{ filemtime(public_path('assets/app.css')) }}">
<link rel="stylesheet" href="{{ asset('assets/ui.css') }}?v={{ filemtime(public_path('assets/ui.css')) }}">
@isset($brand)
<style>:root{--accent:{{ $brand->accent() }};--accent-2:{{ $brand->accentHover() }};--ok:{{ $brand->accent() }};--on-accent:{{ $brand->onAccent() }}}</style>
@endisset
