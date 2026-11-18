@extends('errors.layout')
@section('title', 'Sesi habis')
@section('code', '419')
@section('headline', 'Sesi habis, muat ulang halaman.')
@section('detail')
Halaman terlalu lama dibuka. Muat ulang halaman lalu ulangi tindakanmu. Pesanan yang sudah dibayar tidak terpengaruh.
@endsection
@section('actions')
<button class="btn" type="button" data-refresh>Muat ulang</button><a class="btn btn--light" href="{{ url('/staff') }}">Ke dashboard</a>
@endsection
