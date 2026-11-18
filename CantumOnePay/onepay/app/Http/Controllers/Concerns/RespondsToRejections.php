<?php

namespace App\Http\Controllers\Concerns;

use App\Services\OrderRejected;
use Illuminate\Http\JsonResponse;

/** Pelanggaran aturan bisnis dibalas 422 dengan pesan yang aman ditampilkan ke staf. */
trait RespondsToRejections
{
    private function guarded(callable $action): JsonResponse
    {
        try {
            return response()->json($action());
        } catch (OrderRejected $rejection) {
            return response()->json(['error' => $rejection->getMessage()], 422);
        }
    }
}
