<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentApplyResult;
use App\Enums\PaymentOutcome;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Kasera\KaseraClient;
use App\Services\Kasera\KaseraException;
use App\Services\Kasera\PaymentMethods;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Satu-satunya tempat status pesanan berubah karena pembayaran. Dipakai bersama oleh webhook,
 * sync cadangan, dan expiry, jadi logikanya tidak terpecah.
 */
class PaymentService
{
    public function __construct(private readonly KaseraClient $kasera)
    {
    }

    /** Idempotent: aman dipanggil berulang untuk kejadian yang sama. Mengembalikan true bila ada perubahan. */
    public function applyOutcome(Order $order, PaymentOutcome $outcome, string $actor, ?string $providerRef = null, ?int $paidAmount = null): bool
    {
        return $this->apply($order, $outcome, $actor, $providerRef, $paidAmount) === PaymentApplyResult::Applied;
    }

    /**
     * Terapkan kejadian pembayaran dengan dua pemeriksaan keamanan: id tagihan harus sama dengan yang tersimpan,
     * dan nominal yang dibayar (bila disebutkan) harus sama dengan total pesanan.
     */
    public function apply(Order $order, PaymentOutcome $outcome, string $actor, ?string $providerRef = null, ?int $paidAmount = null): PaymentApplyResult
    {
        return DB::transaction(function () use ($order, $outcome, $actor, $providerRef, $paidAmount) {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);

            $storedRef = $locked->payment?->provider_ref;
            if ($storedRef !== null && $providerRef !== null && $storedRef !== $providerRef) {
                Log::warning('kasera.ref_mismatch', ['order' => $locked->code]);
                return PaymentApplyResult::RefMismatch;
            }
            if ($outcome === PaymentOutcome::Paid && $paidAmount !== null && $paidAmount !== (int) $locked->total) {
                return $this->flagAmountMismatch($locked, $actor, $paidAmount);
            }

            $changed = $outcome === PaymentOutcome::Paid
                ? $this->markPaid($locked, $actor, $providerRef)
                : $this->markUnpaid($locked, $actor);

            return $changed ? PaymentApplyResult::Applied : PaymentApplyResult::Duplicate;
        });
    }

    /** Uang masuk dengan nominal berbeda: jangan dianggap lunas, catat, dan serahkan ke staf. */
    private function flagAmountMismatch(Order $order, string $actor, int $paidAmount): PaymentApplyResult
    {
        if ($order->needs_review) {
            return PaymentApplyResult::Duplicate;
        }
        $order->update(['needs_review' => true]);
        $order->logStatus($order->status, $order->status, $actor, "Nominal pembayaran Rp $paidAmount tidak sama dengan tagihan Rp {$order->total}");
        Log::warning('kasera.amount_mismatch', ['order' => $order->code, 'paid' => $paidAmount, 'total' => $order->total]);
        return PaymentApplyResult::AmountMismatch;
    }

    private function markPaid(Order $order, string $actor, ?string $providerRef): bool
    {
        if ($order->paid_at !== null) {
            return false;
        }

        $order->payment()->update(array_filter([
            'status' => 'paid', 'paid_at' => now(), 'provider_ref' => $providerRef,
        ], fn ($value) => $value !== null));

        $previous = $order->status;
        if ($previous === OrderStatus::PendingPayment) {
            $order->update(['status' => OrderStatus::Paid, 'paid_at' => now()]);
            $order->logStatus($previous, OrderStatus::Paid, $actor);
            return true;
        }

        // Uang masuk setelah pesanan expired atau dibatalkan: tidak masuk dapur, ditandai untuk review dan refund.
        $order->update(['paid_at' => now(), 'needs_review' => true]);
        $order->logStatus($previous, $previous, $actor, 'Pembayaran masuk setelah pesanan ' . $previous->value);
        return true;
    }

    private function markUnpaid(Order $order, string $actor): bool
    {
        if ($order->status !== OrderStatus::PendingPayment) {
            return false;
        }
        $order->update(['status' => OrderStatus::Expired]);
        $order->payment()->where('status', 'pending')->update(['status' => 'expired']);
        $order->logStatus(OrderStatus::PendingPayment, OrderStatus::Expired, $actor);
        app(DiscountService::class)->releaseUsage($order); // pesanan tak jadi dibayar: kuota promo kembali
        return true;
    }

    /**
     * Buat tagihan di Kasera. Memanggil dua kali untuk pesanan yang sama mengembalikan tagihan yang sama:
     * Idempotency-Key terkunci ke kode pesanan dan isi permintaan selalu identik.
     */
    public function ensureGatewayPayment(Order $order): Payment
    {
        $payment = $order->payment()->firstOrFail();
        if ($payment->hasGatewayDetails()) {
            return $payment;
        }

        $response = $this->createOrRecoverTransaction($order, $payment->method);

        $payment->update([
            'provider_ref' => $response['id'] ?? null,
            'details' => ['payment' => $response['payment'] ?? null, 'instructions' => $response['instructions'] ?? null],
            'expires_at' => isset($response['expires_at']) ? date('Y-m-d H:i:s', strtotime((string) $response['expires_at'])) : null,
        ]);
        return $payment->refresh();
    }

    /** Bila Kasera menjawab 409/422 dan menyebut tagihan yang sudah ada, baca tagihan itu, jangan membuat yang baru. */
    private function createOrRecoverTransaction(Order $order, string $method): array
    {
        try {
            return $this->kasera->createTransaction($this->transactionPayload($order, $method), 'order-' . $order->code);
        } catch (KaseraException $error) {
            $existingId = $error->isConflict() ? $error->existingTransactionId() : null;
            if ($existingId === null) {
                throw $error;
            }
            Log::info('kasera.recover_existing', ['order' => $order->code]);
            return $this->kasera->getTransaction($existingId);
        }
    }

    /** Cadangan bila webhook tidak datang. Dibatasi sekali per interval per pesanan. */
    public function syncWithGateway(Order $order): void
    {
        if ($order->status !== OrderStatus::PendingPayment) {
            return;
        }
        $providerRef = $order->payment?->provider_ref;
        if ($providerRef === null || !Cache::add('sync-' . $order->code, 1, (int) config('onepay.kasera.sync_interval'))) {
            return;
        }

        $transaction = $this->kasera->getTransaction($providerRef);
        $outcome = PaymentOutcome::fromGatewayStatus((string) ($transaction['status'] ?? ''));
        if ($outcome !== null) {
            $amount = isset($transaction['amount']) && is_numeric($transaction['amount']) ? (int) $transaction['amount'] : null;
            $this->applyOutcome($order, $outcome, 'system:sync', $transaction['id'] ?? null, $amount);
        }
    }

    /** Pesanan belum dibayar yang lewat batas waktu menjadi expired. */
    public function expireStaleOrders(): int
    {
        $stale = Order::query()->where('status', OrderStatus::PendingPayment)->where('expires_at', '<', now())->get();
        foreach ($stale as $order) {
            $this->applyOutcome($order, PaymentOutcome::Failed, 'system:expiry');
        }
        return $stale->count();
    }

    /**
     * Isi permintaan harus deterministik: hanya bergantung pada data pesanan yang tersimpan, bukan jam sekarang,
     * karena mengubah isi dengan Idempotency-Key yang sama ditolak Kasera.
     */
    private function transactionPayload(Order $order, string $method): array
    {
        $isBank = PaymentMethods::find($method)['type'] === PaymentMethods::TYPE_BANK;
        $name = $order->customer_name ?: 'Meja ' . $order->table_label;
        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'example.com';

        return [
            'amount' => $order->total,
            'description' => 'Pesanan ' . $order->code,
            'external_id' => $order->code,
            'merchant_ref' => $order->code,
            'customer' => [
                'name' => $isBank ? $this->asciiBankName($name) : $name,
                'email' => 'noreply@' . $host,
                'phone' => $this->normalizePhone((string) $order->customer_phone),
            ],
            'payment_methods' => [$method],
            'return_url' => route('pay.show', $order),
            'expires_in_minutes' => $this->expiryMinutes($order),
        ] + $this->deliveryTerms($order);
    }

    /** Masa berlaku tetap seperti saat pesanan dibuat, walau tagihan baru berhasil dibuat belakangan. */
    private function expiryMinutes(Order $order): int
    {
        return max(1, (int) round($order->created_at->diffInSeconds($order->expires_at, true) / 60));
    }

    /** Pembayaran di atas ambang wajib menyertakan tanggal layanan (hari pesanan dibuat, WIB) dan penanda ketentuan ditampilkan. */
    private function deliveryTerms(Order $order): array
    {
        if (!self::requiresDeliveryTerms((int) $order->total)) {
            return [];
        }
        return [
            'delivery_date' => $order->created_at->copy()->timezone('Asia/Jakarta')->toDateString(),
            'delivery_terms_shown' => true,
        ];
    }

    public static function requiresDeliveryTerms(int $total): bool
    {
        return (bool) config('onepay.kasera.delivery_terms') && $total > (int) config('onepay.kasera.delivery_threshold');
    }

    /** Ambang dan teks ketentuan untuk ditampilkan ke pelanggan, atau null bila aturan dimatikan. */
    public static function deliveryTermsNotice(): ?array
    {
        if (!config('onepay.kasera.delivery_terms')) {
            return null;
        }
        return ['threshold' => (int) config('onepay.kasera.delivery_threshold'), 'text' => (string) config('onepay.kasera.delivery_terms_text')];
    }

    /** Bank hanya menerima ASCII maksimal 30 karakter. */
    private function asciiBankName(string $name): string
    {
        $ascii = trim(preg_replace("/[^A-Za-z0-9 .,'\\-]/", '', Str::ascii($name)) ?? '');
        return Str::limit($ascii !== '' ? $ascii : 'Customer', 30, '');
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/[^\d+]/', '', trim($phone)) ?? '';
        return match (true) {
            $digits === '', str_starts_with($digits, '+') => $digits,
            str_starts_with($digits, '0') => '+62' . substr($digits, 1),
            str_starts_with($digits, '62') => '+' . $digits,
            default => '+62' . $digits,
        };
    }

    public function logGatewayFailure(Order $order, KaseraException $error): void
    {
        Log::channel('single')->warning('Gagal membuat tagihan ' . $order->code . ': ' . $error->getMessage());
    }
}
