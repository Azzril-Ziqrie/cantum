<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\DiningTable;
use App\Models\TableScan;
use App\Services\BranchSchedule;
use App\Services\DiscountService;
use App\Services\Kasera\PaymentMethods;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/** Halaman yang terbuka saat pelanggan scan QR atau tap NFC di meja. */
class MenuController extends Controller
{
    public function show(Request $request, string $token, OrderService $orders, DiscountService $discounts, BranchSchedule $schedules): View
    {
        $table = DiningTable::findOrderable($token);
        abort_if($table === null, 404, 'QR meja tidak valid.');
        TableScan::record($table, $request);

        $brand = Brand::current();
        $settings = $table->branch->effectiveSettings($brand);
        $schedule = $schedules->statusFor($table->branch);
        $methods = array_intersect_key(PaymentMethods::all(), array_flip($settings->enabledMethods));

        return view('menu', [
            'brand' => $brand,
            'table' => $table,
            'menu' => $orders->branchMenu($table->branch_id),
            'promos' => $discounts->publicPromos($table->branch_id),
            'schedule' => $schedule,
            'prepMinutes' => (int) $table->branch->prep_minutes,
            'categories' => Category::query()->where('is_active', true)->pluck('name', 'id'),
            'clientConfig' => [
                'token' => $table->token,
                'methods' => array_map(fn ($m) => ['label' => $m['label']], $methods),
                'tax' => $settings->taxPercent,
                'service' => $settings->servicePercent,
                'rounding' => $settings->roundingTo,
                'isOpen' => $schedule['orderable'],
                'closedMessage' => $schedule['message'],
                'endpoint' => route('order.store'),
                'promoUrl' => route('promo.check'),
                'deliveryTerms' => PaymentService::deliveryTermsNotice(),
            ],
        ]);
    }
}
