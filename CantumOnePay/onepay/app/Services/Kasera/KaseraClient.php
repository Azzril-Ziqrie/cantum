<?php

namespace App\Services\Kasera;

use App\Services\GatewaySettings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Klien HTTP Kasera Pay. Docs: https://pay.kasera.id/docs
 *
 * Retry terbatas hanya untuk error jaringan dan 5xx. Aman untuk POST karena Idempotency-Key terkunci ke pesanan.
 * Log tidak pernah memuat kunci API, secret, atau data pelanggan.
 */
class KaseraClient
{
    public function createTransaction(array $payload, string $idempotencyKey): array
    {
        return $this->send('POST', '/transactions', fn (PendingRequest $http) => $http
            ->withHeaders(['Idempotency-Key' => $idempotencyKey])
            ->post('/transactions', $payload), $idempotencyKey);
    }

    public function getTransaction(string $paymentRequestId): array
    {
        $path = '/transactions/' . rawurlencode($paymentRequestId);
        return $this->send('GET', '/transactions/{id}', fn (PendingRequest $http) => $http->get($path));
    }

    /** Dipakai tombol "uji koneksi": berhasil bila kunci API diterima Kasera. */
    public function listPaymentMethods(): array
    {
        return $this->send('GET', '/payment_methods', fn (PendingRequest $http) => $http->get('/payment_methods'));
    }

    private function send(string $method, string $route, callable $request, ?string $idempotencyKey = null): array
    {
        $startedAt = hrtime(true);
        try {
            $response = $request($this->http());
        } catch (ConnectionException $error) {
            $this->log('error', $method, $route, null, $startedAt, $idempotencyKey, 'koneksi gagal');
            throw new KaseraException('Koneksi ke Kasera gagal: ' . $error->getMessage());
        }

        $this->log($response->failed() ? 'warning' : 'info', $method, $route, $response->status(), $startedAt, $idempotencyKey);
        return $this->parse($response);
    }

    private function parse(Response $response): array
    {
        $body = $response->json();
        if (!is_array($body)) {
            throw new KaseraException("Respons Kasera tidak valid (HTTP {$response->status()})", $response->status());
        }
        if ($response->failed()) {
            $message = $body['message'] ?? $body['error']['message'] ?? 'permintaan ditolak';
            throw new KaseraException("Kasera ({$response->status()}): $message", $response->status(), $body);
        }
        return $body;
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl(rtrim(config('onepay.kasera.api_base_url'), '/'))
            ->withToken(app(GatewaySettings::class)->apiKey())
            ->acceptJson()
            ->asJson()
            ->connectTimeout((int) config('onepay.kasera.connect_timeout'))
            ->timeout((int) config('onepay.kasera.timeout'))
            ->retry((array) config('onepay.kasera.retry_backoff_ms'), when: fn (Throwable $error) => $this->isTransient($error), throw: false);
    }

    /** Error jaringan dan 5xx layak dicoba lagi; 4xx berarti permintaannya yang salah. */
    private function isTransient(Throwable $error): bool
    {
        if ($error instanceof ConnectionException) {
            return true;
        }
        return $error instanceof RequestException && $error->response->serverError();
    }

    /** Log terstruktur: hanya metadata permintaan. */
    private function log(string $level, string $method, string $route, ?int $status, int $startedAt, ?string $idempotencyKey, ?string $note = null): void
    {
        Log::log($level, 'kasera.request', array_filter([
            'method' => $method,
            'route' => $route,
            'status' => $status,
            'duration_ms' => (int) round((hrtime(true) - $startedAt) / 1e6),
            'idempotency_key' => $idempotencyKey,
            'note' => $note,
        ], fn ($value) => $value !== null));
    }
}
