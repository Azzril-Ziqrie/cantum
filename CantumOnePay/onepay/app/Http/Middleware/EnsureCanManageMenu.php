<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Halaman admin menu (harga, HPP, ekspor CSV) hanya untuk owner dan manager; aksi tetap dicek lagi di service per cabang. */
class EnsureCanManageMenu
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->role->canManageMenu(), 403);
        return $next($request);
    }
}
