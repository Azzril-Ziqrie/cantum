{{-- Filter cabang dan tanggal dalam satu baris. Mode rentang (from/to) atau satu tanggal (date). --}}
@php
    $today = now()->toDateString();
    $presets = [
        'Hari ini' => [$today, $today],
        '7 hari' => [now()->subDays(6)->toDateString(), $today],
        '30 hari' => [now()->subDays(29)->toDateString(), $today],
        'Bulan ini' => [now()->startOfMonth()->toDateString(), $today],
    ];
    $branchValue = $branchId ?? ($filter->branchId ?? 'all');
@endphp
<form class="rp-filters" method="get" action="{{ $action }}">
  <label class="field"><span class="label">Cabang</span>
    <select name="branch" data-autosubmit>
      @if ($branches->count() > 1)<option value="all" @selected($branchValue === 'all')>Semua cabang</option>@endif
      @foreach ($branches as $branch)<option value="{{ $branch->id }}" @selected((string) $branchValue === (string) $branch->id)>{{ $branch->name }}</option>@endforeach
    </select>
  </label>
  @if ($withRange)
    <label class="field"><span class="label">Dari</span><input type="date" name="from" value="{{ $filter->from->toDateString() }}" max="{{ $today }}"></label>
    <label class="field"><span class="label">Sampai</span><input type="date" name="to" value="{{ $filter->to->toDateString() }}" max="{{ $today }}"></label>
  @else
    <label class="field"><span class="label">Tanggal</span><input type="date" name="date" value="{{ $date }}" max="{{ $today }}"></label>
  @endif
  <button class="btn btn--small" type="submit">Terapkan</button>
  @if ($withRange)
    <span class="tabs rp-presets">
      @foreach ($presets as $label => [$from, $to])
        <a class="rp-preset" href="{{ $action }}?{{ http_build_query(['branch' => $branchValue, 'from' => $from, 'to' => $to]) }}">{{ $label }}</a>
      @endforeach
    </span>
  @endif
</form>
