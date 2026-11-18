<?php

namespace App\Http\Controllers;

use App\Enums\PaymentApplyResult;
use App\Enums\PaymentOutcome;
use App\Models\Order;
use App\Models\PaymentWebhook;
use App\Services\Kasera\WebhookSignature;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Webhook Kasera Pay. Satu-satunya jalur (selain sync ke gateway) yang boleh menandai pesanan `paid`.
 * Daftarkan di dashboard Kasera: Developer > Webhooks.
 */
class KaseraWebhookController extends Controller
{
    private const MAX_BODY_BYTES = 262144;
    private const REJECTED_BODY_BYTES = 500;

    /** Nama field referensi pesanan belum 100% pasti; payload asli dicatat di payment_webhooks untuk disesuaikan. */
    private const REFERENCE_FIELDS = ['external_id', 'merchant_ref', 'reference_id', 'reference', 'order_id', 'order_code'];

    public function __invoke(Request $request, PaymentService $payments, WebhookSignature $signature): Response
    {
        $rawBody = $request->getContent();
        if (strlen($rawBody) > self::MAX_BODY_BYTES) {
            return response('payload too large', 413); // jauh di atas ukuran payload Kasera; tidak diverifikasi, tidak disimpan
        }

        $valid = $signature->isValidRequest($rawBody, (string) $request->header('Kasera-Signature-V1'), (string) $request->header('Kasera-Signature'));
        if (!$valid) {
            // Pengirim tak dikenal: simpan hanya potongan kecil supaya log tidak bisa dipenuhi penyerang.
            $this->record(false, null, null, 'signature_invalid', substr($rawBody, 0, self::REJECTED_BODY_BYTES));
            return response('invalid signature', 401);
        }

        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            $this->record(true, null, null, 'bad_json', $rawBody);
            return response('invalid payload', 400);
        }

        $event = $payload['event'] ?? $payload['type'] ?? null;
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : $payload;
        $code = $this->findOrderReference($payload, $data);

        $order = $code !== null ? Order::query()->where('code', $code)->first() : null;
        $outcome = $event !== null ? PaymentOutcome::fromWebhookEvent((string) $event) : null;

        // Event tanpa referensi, pesanan tak dikenal, atau bukan event pembayaran: di-ack supaya Kasera tidak mengirim ulang terus.
        if ($order === null || $outcome === null) {
            $this->record(true, $event, $code, 'ignored', $rawBody);
            return response('acknowledged', 200);
        }

        try {
            $providerRef = isset($data['id']) ? (string) $data['id'] : null;
            $result = $payments->apply($order, $outcome, 'system:webhook', $providerRef, $this->reportedAmount($data));
            $this->record(true, $event, $code, $result->value, $rawBody);
            // Id tidak cocok atau nominal berbeda tidak akan membaik bila dikirim ulang: di-ack, penanganannya manual.
            return response($this->wasProcessed($result) ? 'ok' : 'acknowledged', 200);
        } catch (Throwable $error) {
            Log::error('Webhook gagal diproses untuk ' . $code . ': ' . $error->getMessage());
            $this->record(true, $event, $code, 'error', $rawBody);
            return response('internal error', 500); // 500 supaya Kasera mengirim ulang
        }
    }

    private function findOrderReference(array $payload, array $data): ?string
    {
        foreach (self::REFERENCE_FIELDS as $field) {
            $value = $data[$field] ?? $payload[$field] ?? null;
            if (!empty($value)) {
                return (string) $value;
            }
        }
        return null;
    }

    /** Nominal yang dibayar, bila payload menyebutkannya. Nama field belum pasti, jadi beberapa kemungkinan dicoba. */
    private function reportedAmount(array $data): ?int
    {
        foreach (['amount_paid', 'paid_amount', 'amount'] as $field) {
            if (isset($data[$field]) && is_numeric($data[$field])) {
                return (int) $data[$field];
            }
        }
        return null;
    }

    private function wasProcessed(PaymentApplyResult $result): bool
    {
        return in_array($result, [PaymentApplyResult::Applied, PaymentApplyResult::Duplicate], true);
    }

    private function record(bool $valid, ?string $event, ?string $code, string $result, string $body): void
    {
        PaymentWebhook::create(['signature_valid' => $valid, 'event' => $event, 'order_code' => $code, 'result' => $result, 'body' => $body]);
    }
}
