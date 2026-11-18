<link rel="stylesheet" href="{{ asset('assets/admin-settings.css') }}?v={{ filemtime(public_path('assets/admin-settings.css')) }}">
<nav class="adm-subnav" aria-label="Pengaturan">
  <a href="{{ route('admin.settings') }}" @if (request()->routeIs('admin.settings')) aria-current="page" @endif>Pengaturan</a>
  <a href="{{ route('admin.settings.audit') }}" @if (request()->routeIs('admin.settings.audit')) aria-current="page" @endif>Log audit</a>
  <a href="{{ route('admin.settings.webhooks') }}" @if (request()->routeIs('admin.settings.webhooks')) aria-current="page" @endif>Log webhook</a>
</nav>
