@extends('layouts.staff', ['title' => 'Log webhook'])

@section('content')
<div class="content adm">
  @include('admin.settings-parts.subnav')

  <form method="get" class="card adm-grid">
    <label class="adm-field"><span class="label">Hasil</span>
      <select name="result"><option value="">Semua</option>
        @foreach (['applied', 'duplicate', 'ignored', 'signature_invalid', 'bad_json', 'error'] as $result)<option value="{{ $result }}" @selected(($filters['result'] ?? '') === $result)>{{ $result }}</option>@endforeach
      </select>
    </label>
    <label class="adm-field"><span class="label">Signature</span>
      <select name="signature"><option value="">Semua</option>
        <option value="valid" @selected(($filters['signature'] ?? '') === 'valid')>Valid</option>
        <option value="invalid" @selected(($filters['signature'] ?? '') === 'invalid')>Tidak valid</option>
      </select>
    </label>
    <label class="adm-field"><span class="label">Kode pesanan</span><input type="text" name="code" value="{{ $filters['code'] ?? '' }}" maxlength="20"></label>
    <label class="adm-field"><span class="label">Dari</span><input type="date" name="from" value="{{ $filters['from'] ?? '' }}"></label>
    <label class="adm-field"><span class="label">Sampai</span><input type="date" name="to" value="{{ $filters['to'] ?? '' }}"></label>
    <div style="align-self:end"><button class="btn btn--small" type="submit">Terapkan</button></div>
  </form>

  <div class="card adm-scroll">
    <table class="adm-table">
      <thead><tr><th>Diterima</th><th>Signature</th><th>Event</th><th>Pesanan</th><th>Hasil</th><th>Body</th></tr></thead>
      <tbody>
        @forelse ($logs as $log)
          <tr>
            <td class="num">{{ \Illuminate\Support\Carbon::parse($log->received_at)->format('d/m/Y H:i:s') }}</td>
            <td>{{ $log->signature_valid ? 'valid' : 'TIDAK valid' }}</td>
            <td>{{ $log->event }}</td>
            <td>{{ $log->order_code }}</td>
            <td>{{ $log->result }}</td>
            <td><details><summary class="label">Lihat</summary><pre class="adm-body">{{ $log->body }}</pre></details></td>
          </tr>
        @empty
          <tr><td colspan="6" class="muted">Belum ada webhook masuk.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  {{ $logs->links('admin.settings-parts.pager') }}
</div>
@endsection
