<?php

namespace App\Http\Controllers;

use App\Models\DiningTable;
use App\Services\OrderRejected;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Pratinjau potongan untuk keranjang. Hanya membaca; pesanan sebenarnya menghitung ulang di server. */
class PromoController extends Controller
{
    public function check(Request $request, OrderService $orders): JsonResponse
    {
        $table = DiningTable::findOrderable((string) $request->input('token'));
        if ($table === null) {
            return response()->json(['error' => 'QR meja tidak valid.'], 404);
        }

        try {
            $quote = $orders->quote($table, (array) $request->input('cart', []), $request->input('promo_code'));
        } catch (OrderRejected $rejection) {
            return response()->json(['error' => $rejection->getMessage()], 422);
        }

        return response()->json([
            'discount' => $quote['discount'],
            'label' => $quote['discount_label'],
            'total' => $quote['total'],
        ]);
    }
}
