@if ($paginator->hasPages())
  <nav class="adm-pager" aria-label="Halaman">
    @if ($paginator->onFirstPage())<span class="muted">Sebelumnya</span>@else<a href="{{ $paginator->previousPageUrl() }}">Sebelumnya</a>@endif
    <span class="is-current num">{{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>
    @if ($paginator->hasMorePages())<a href="{{ $paginator->nextPageUrl() }}">Berikutnya</a>@else<span class="muted">Berikutnya</span>@endif
  </nav>
@endif
