@extends('errors.layout')
@section('title', 'Tidak ada akses')
@section('code', '403')
@section('headline', 'Kamu tidak punya akses ke halaman ini.')
@section('detail')
Akunmu tidak diizinkan membuka halaman ini. Hubungi owner bila seharusnya bisa.
@endsection
@section('actions')
<a class="btn" href="{{ url('/staff') }}">Ke dashboard</a><button class="btn btn--light" type="button" data-back>Kembali</button>
@endsection
