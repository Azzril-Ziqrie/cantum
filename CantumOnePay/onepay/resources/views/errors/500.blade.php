@extends('errors.layout')
@section('title', 'Terjadi kesalahan')
@section('code', '500')
@section('headline', 'Terjadi kesalahan di server.')
@section('detail')
Masalahnya sudah kami catat. Coba lagi sebentar. Bila kamu sudah membayar, pesananmu tetap aman dan akan diproses.
@endsection
@section('actions')
<button class="btn" type="button" data-reload>Coba lagi</button><a class="btn btn--light" href="{{ url('/') }}">Beranda</a>
@endsection
