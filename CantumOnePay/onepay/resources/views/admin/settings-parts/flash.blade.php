@if (session('status'))<p class="notice" role="status">{{ session('status') }}</p>@endif
@if (session('gateway_error'))<p class="notice notice--warn" role="alert">{{ session('gateway_error') }}</p>@endif
@error('form')<p class="notice notice--warn" role="alert">{{ $message }}</p>@enderror
@if ($errors->any() && !$errors->has('form'))
  <p class="notice notice--warn" role="alert">{{ $errors->first() }}</p>
@endif
