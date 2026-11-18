<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header keamanan untuk semua halaman web. Skrip inline hanya jalan bila membawa nonce per-request,
 * jadi HTML yang berhasil disisipi penyerang tidak bisa menjalankan skrip.
 */
class SecurityHeaders
{
    private const HSTS_SECONDS = 15552000; // 180 hari

    public function handle(Request $request, Closure $next): Response
    {
        $nonce = base64_encode(random_bytes(16));
        View::share('cspNonce', $nonce);

        $response = $next($request);

        foreach ($this->headers($nonce, $request->isSecure()) as $name => $value) {
            $response->headers->set($name, $value, false);
        }
        return $response;
    }

    /** @return array<string,string> */
    private function headers(string $nonce, bool $secure): array
    {
        $headers = [
            'Content-Security-Policy' => $this->contentSecurityPolicy($nonce, $secure),
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
        ];
        if ($secure) {
            $headers['Strict-Transport-Security'] = 'max-age=' . self::HSTS_SECONDS;
        }
        return $headers;
    }

    private function contentSecurityPolicy(string $nonce, bool $secure): string
    {
        $directives = [
            "default-src 'self'",
            "script-src 'self' 'nonce-$nonce' https://cdnjs.cloudflare.com",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "font-src 'self' https://fonts.gstatic.com",
            "img-src 'self' data: blob:",
            "connect-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ];
        if ($secure) {
            $directives[] = 'upgrade-insecure-requests';
        }
        return implode('; ', $directives);
    }
}
