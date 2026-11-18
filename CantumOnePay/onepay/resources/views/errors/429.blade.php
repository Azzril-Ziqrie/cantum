@extends('errors.layout')
@section('title', 'Terlalu banyak permintaan')
@section('code', '429')
@section('headline', 'Terlalu banyak permintaan.')
@section('detail')
Tunggu sebentar lalu coba lagi. Bila terus muncul, hubungi staf.
@endsection
@section('actions')
<button class="btn" type="button" data-back>Kembali</button>
@endsection
