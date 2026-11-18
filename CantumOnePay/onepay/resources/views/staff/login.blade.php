@extends('layouts.app', ['title' => 'Masuk staf'])

@section('content')
<main class="wrap">
  <p class="label">Dashboard staf</p>
  <h1 class="display">Masuk.</h1>
  <form method="post" action="{{ route('staff.login') }}" class="stack">
    @csrf
    @error('email')<p class="notice notice--warn" role="alert">{{ $message }}</p>@enderror
    <label class="field"><span class="label">Email</span><input type="email" name="email" value="{{ old('email') }}" required autocomplete="username"></label>
    <label class="field"><span class="label">Kata sandi</span><input type="password" name="password" required autocomplete="current-password"></label>
    <button class="btn btn--block" type="submit">Masuk</button>
  </form>
</main>
@endsection
