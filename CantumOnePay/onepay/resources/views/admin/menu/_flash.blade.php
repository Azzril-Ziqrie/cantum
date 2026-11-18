@if (session('status'))<p class="notice" role="status">{{ session('status') }}</p>@endif
@if ($errors->any())
  <div class="notice notice--warn" role="alert">@foreach ($errors->all() as $message)<div>{{ $message }}</div>@endforeach</div>
@endif
