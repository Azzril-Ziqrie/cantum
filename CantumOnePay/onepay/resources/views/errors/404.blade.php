@php($invalidQr = $exception->getMessage() === 'QR meja tidak valid.')
@extends('errors.layout')
@section('title', $invalidQr ? 'QR tidak valid' : 'Tidak ditemukan')
@section('code', '404')
@section('headline', $invalidQr ? 'QR meja tidak valid.' : 'Halaman tidak ditemukan.')
@section('detail')
{{ $invalidQr ? 'QR ini sudah tidak berlaku atau meja dinonaktifkan. Minta bantuan staf untuk memindai ulang.' : 'Alamat yang kamu buka tidak ada atau sudah dipindahkan.' }}
@endsection
@section('actions')
@unless ($invalidQr)<a class="btn" href="{{ url('/staff') }}">Ke dashboard staf</a>@endunless
<button class="btn btn--light" type="button" data-back>Kembali</button>
@endsection
