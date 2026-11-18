@extends('layouts.staff', ['title' => 'Log audit'])

@section('content')
<div class="content adm">
  @include('admin.settings-parts.subnav')

  <form method="get" class="card adm-grid">
    <label class="adm-field"><span class="label">Pengguna</span>
      <select name="user_id"><option value="">Semua</option>
        @foreach ($users as $user)<option value="{{ $user->id }}" @selected((string) ($filters['user_id'] ?? '') === (string) $user->id)>{{ $user->name }}</option>@endforeach
      </select>
    </label>
    <label class="adm-field"><span class="label">Aksi</span><input type="text" name="action" value="{{ $filters['action'] ?? '' }}" placeholder="mis. order.cancel"></label>
    <label class="adm-field"><span class="label">Dari</span><input type="date" name="from" value="{{ $filters['from'] ?? '' }}"></label>
    <label class="adm-field"><span class="label">Sampai</span><input type="date" name="to" value="{{ $filters['to'] ?? '' }}"></label>
    <div style="align-self:end"><button class="btn btn--small" type="submit">Terapkan</button></div>
  </form>

  <div class="card adm-scroll">
    <table class="adm-table">
      <thead><tr><th>Waktu</th><th>Pengguna</th><th>Aksi</th><th>Objek</th><th>Sebelum</th><th>Sesudah</th></tr></thead>
      <tbody>
        @forelse ($logs as $log)
          <tr>
            <td class="num">{{ \Illuminate\Support\Carbon::parse($log->created_at)->format('d/m/Y H:i') }}</td>
            <td>{{ $log->user?->name ?? 'sistem' }}</td>
            <td>{{ $log->action }}</td>
            <td>{{ $log->entity }} #{{ $log->entity_id }}</td>
            <td>{{ $log->old_value }}</td>
            <td>{{ $log->new_value }}</td>
          </tr>
        @empty
          <tr><td colspan="6" class="muted">Belum ada catatan.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  {{ $logs->links('admin.settings-parts.pager') }}
</div>
@endsection
