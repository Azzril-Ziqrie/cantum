<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Akun yang dinonaktifkan kehilangan akses pada request berikutnya, bukan hanya saat login. */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user === null || $user->is_active !== false) {
            return $next($request);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $request->expectsJson()
            ? response()->json(['error' => 'Akun dinonaktifkan.'], 401)
            : redirect()->route('login');
    }
}
