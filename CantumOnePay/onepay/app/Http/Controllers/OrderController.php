<?php

namespace App\Http\Controllers;

use App\Models\DiningTable;
use App\Services\Kasera\KaseraException;
use App\Services\OrderRejected;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    /** Buat pesanan dari keranjang. Mengembalikan alamat halaman bayar. */
    public function store(Request $request, OrderService $orders, PaymentService $payments): JsonResponse
    {
        $table = DiningTable::findOrderable((string) $request->input('token'));
        if ($table === null) {
            return response()->json(['error' => 'QR meja tidak valid.'], 404);
        }

        try {
            $order = $orders->create(
                $table,
                (array) $request->input('cart', []),
                $request->only('name', 'phone'),
                (string) $request->input('method'),
                (string) $request->ip(),
                $request->input('promo_code'),
            );
        } catch (OrderRejected $rejection) {
            return response()->json(['error' => $rejection->getMessage()], 422);
        }

        // Pesanan sudah tersimpan. Bila Kasera gagal sekarang, halaman bayar akan mencoba lagi.
        try {
            $payments->ensureGatewayPayment($order);
        } catch (KaseraException $error) {
            $payments->logGatewayFailure($order, $error);
        }

        return response()->json(['code' => $order->code, 'redirect' => route('pay.show', $order)]);
    }
}
