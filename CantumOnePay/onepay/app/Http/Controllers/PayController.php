<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\PrepStatus;
use App\Models\Brand;
use App\Models\Order;
use App\Services\Kasera\KaseraException;
use App\Services\Kasera\PaymentMethods;
use App\Services\PaymentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Halaman bayar dan status pesanan pelanggan. Konfirmasi hanya dari webhook atau sync, tidak pernah dari halaman ini. */
class PayController extends Controller
{
    public function show(Order $order, PaymentService $payments): View
    {
        $gatewayError = false;
        if ($order->status === OrderStatus::PendingPayment) {
            try {
                $payments->ensureGatewayPayment($order);
            } catch (KaseraException $error) {
                $payments->logGatewayFailure($order, $error);
                $gatewayError = true;
            }
        }

        $order->load('items.modifiers', 'payment');

        return view('pay', [
            'brand' => Brand::current(),
            'order' => $order,
            'payment' => $order->payment,
            'method' => PaymentMethods::find($order->payment->method),
            'gatewayError' => $gatewayError,
            'subline' => $this->subline($order),
            'progress' => $this->progress($order),
        ]);
    }

    /** Tahap yang dilalui pesanan setelah dibayar, untuk ditampilkan sebagai progres di HP pelanggan. */
    private function progress(Order $order): array
    {
        $current = match ($order->status) {
            OrderStatus::Paid => 0,
            OrderStatus::Preparing => 1,
            OrderStatus::Served => 2,
            OrderStatus::Completed => 3,
            default => null,
        };
        if ($current === null) {
            return [];
        }

        $allReady = $order->items->isNotEmpty() && $order->items->every(fn ($item) => $item->prep_status === PrepStatus::Ready);
        $labels = ['Dibayar', $allReady && $order->status === OrderStatus::Preparing ? 'Siap disajikan' : 'Diproses', 'Disajikan', 'Selesai'];

        return array_map(fn (string $label, int $index) => [
            'label' => $label,
            'state' => $order->status === OrderStatus::Completed || $index < $current ? 'done' : ($index === $current ? 'current' : 'todo'),
        ], $labels, array_keys($labels));
    }

    /** Polling dari halaman bayar. Dengan sync=1, tanya Kasera sebagai cadangan webhook. */
    public function status(Request $request, Order $order, PaymentService $payments): JsonResponse
    {
        if ($request->boolean('sync')) {
            try {
                $payments->syncWithGateway($order);
            } catch (KaseraException $error) {
                $payments->logGatewayFailure($order, $error);
            }
            $order->refresh();
        }

        return response()->json(['status' => $order->status->value, 'needs_review' => $order->needs_review]);
    }

    private function subline(Order $order): string
    {
        return match ($order->status) {
            OrderStatus::Paid => 'Pesananmu sudah masuk ke dapur.',
            OrderStatus::Preparing => 'Pesananmu sedang dibuat.',
            OrderStatus::Served => 'Selamat menikmati.',
            OrderStatus::Completed => 'Terima kasih sudah memesan.',
            OrderStatus::Cancelled => $order->needs_review ? 'Pembayaran sudah masuk; tim kami akan memprosesnya.' : 'Pesanan dibatalkan.',
            OrderStatus::Expired => $order->needs_review
                ? 'Pembayaran masuk setelah batas waktu. Tim kami akan menghubungi atau mengembalikan dana.'
                : 'Silakan pindai QR meja untuk memesan lagi.',
            OrderStatus::PendingPayment => '',
        };
    }
}
