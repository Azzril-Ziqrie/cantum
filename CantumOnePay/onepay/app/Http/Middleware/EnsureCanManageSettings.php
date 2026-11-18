<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Pengguna, pengaturan, gateway, dan log: hanya role yang boleh mengelola pengaturan (owner). */
class EnsureCanManageSettings
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->role->canManageSettings(), 403);
        return $next($request);
    }
}
