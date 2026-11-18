@extends('layouts.staff', ['title' => 'Laporan'])

@section('top')
  <a class="btn btn--light btn--small" href="{{ route('admin.reports.close', ['branch' => $filter->branchId ?? 'all']) }}">Tutup harian</a>
  <a class="btn btn--light btn--small" href="{{ route('admin.reports.csv', $filter->queryParams()) }}">Ekspor CSV</a>
  <a class="btn btn--small" href="{{ route('admin.reports.print', $filter->queryParams()) }}" target="_blank" rel="noopener">Cetak / PDF</a>
@endsection

@section('content')
<link rel="stylesheet" href="{{ asset('assets/reports.css') }}?v={{ filemtime(public_path('assets/reports.css')) }}">
<div class="content rp">
  @include('admin.reports._filters', ['action' => route('admin.reports'), 'withRange' => true])

  <p class="label rp-scope">{{ $branchName }} · {{ $filter->from->translatedFormat('d M Y') }} – {{ $filter->to->translatedFormat('d M Y') }}</p>

  @include('admin.reports._report')
</div>
<div class="rp-tip" id="rpTip" role="tooltip" hidden></div>
<script src="{{ asset('assets/reports.js') }}?v={{ filemtime(public_path('assets/reports.js')) }}" defer></script>
@endsection
