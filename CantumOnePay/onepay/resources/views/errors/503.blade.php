@extends('errors.layout')
@section('title', 'Sedang pemeliharaan')
@section('code', '503')
@section('headline', 'Sedang dalam pemeliharaan.')
@section('detail')
Layanan akan kembali sebentar lagi. Silakan coba lagi dalam beberapa menit.
@endsection
@section('actions')
<button class="btn" type="button" data-reload>Muat ulang</button>
@endsection
