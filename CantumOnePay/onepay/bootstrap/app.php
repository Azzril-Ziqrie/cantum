<?php

use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\SecurityHeaders;
use App\Services\Kasera\WebhookSignature;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Webhook diamankan signature, bukan CSRF.
        $middleware->validateCsrfTokens(except: ['kasera-webhook']);
        $middleware->redirectGuestsTo('/staff/login');
        // Sesi tidak berlaku lagi bila kata sandi pengguna diganti; akun nonaktif langsung ditolak; header keamanan di semua halaman.
        $middleware->authenticateSessions();
        $middleware->web(append: [EnsureUserIsActive::class]);
        // Global, supaya halaman 404/405 pun membawa header keamanan.
        $middleware->append(SecurityHeaders::class);
        // Di produksi hanya host APP_URL yang dilayani: header Host palsu tidak bisa mengubah tautan (QR, return_url pembayaran).
        $middleware->trustHosts(
            at: fn () => app()->isProduction() && ($host = parse_url((string) config('app.url'), PHP_URL_HOST))
                ? ['^' . preg_quote($host, '/') . '$']
                : [],
            subdomains: false,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
