<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\DiningTable;
use App\Models\Item;
use App\Models\Order;
use App\Services\Kasera\PaymentMethods;
use Illuminate\Support\Facades\DB;

/** Pembuatan pesanan. Harga dihitung ulang dari database; nilai dari browser tidak dipercaya. */
class OrderService
{
    public function __construct(private readonly DiscountService $discounts)
    {
    }

    /** Menu yang bisa dipesan di cabang, siap dikirim ke browser. */
    public function branchMenu(int $branchId): array
    {
        return Item::orderableAt($branchId)
            ->with('modifierGroups.modifiers', 'bundleComponents.component')
            ->get()
            ->map(fn (Item $item) => [
                'id' => $item->id,
                'category_id' => $item->category_id,
                'name' => $item->name,
                'description' => $item->description,
                'is_best_seller' => $item->is_best_seller,
                'photo_url' => $item->photoUrl(),
                'is_bundle' => $item->is_bundle,
                'components' => $item->bundleComponents->map(fn ($c) => ['name' => $c->component->name, 'qty' => $c->qty])->all(),
                'price' => (int) $item->effective_price,
                'groups' => $item->modifierGroups->map(fn ($group) => [
                    'id' => $group->id,
                    'name' => $group->name,
                    'required' => $group->required,
                    'options' => $group->modifiers->map(fn ($m) => ['id' => $m->id, 'name' => $m->name, 'price_delta' => $m->price_delta])->all(),
                ])->all(),
            ])->all();
    }

    public function create(DiningTable $table, array $cart, array $customer, string $method, string $clientIp, ?string $promoCode = null): Order
    {
        app(BranchSchedule::class)->assertOrderable($table->branch);
        $this->assertNotFlooding($clientIp);

        $settings = $table->branch->effectiveSettings();
        $lines = $this->priceCart($table->branch_id, $cart);
        $applied = $this->discounts->resolve($lines, $table->branch_id, $promoCode);
        $totals = $this->totals($lines, $settings, $applied?->amount ?? 0);
        $this->assertMethodAllowed($method, $totals['total'], $settings);

        return DB::transaction(function () use ($table, $lines, $customer, $method, $totals, $clientIp, $applied) {
            if ($applied !== null) {
                $this->discounts->reserveUsage($applied);
            }
            $order = $this->insertOrder($table, $customer, $totals + $this->discountColumns($applied), $clientIp);
            $this->insertLines($order, $lines);
            $order->payment()->create(['method' => $method, 'amount' => $totals['total']]);
            $order->logStatus(null, OrderStatus::PendingPayment, 'customer');
            return $order;
        });
    }

    /** Pratinjau total untuk keranjang, termasuk promo otomatis atau kode yang diketik. Tidak menyimpan apa pun. */
    public function quote(DiningTable $table, array $cart, ?string $promoCode): array
    {
        $lines = $this->priceCart($table->branch_id, $cart);
        $applied = $this->discounts->resolve($lines, $table->branch_id, $promoCode);

        return $this->totals($lines, $table->branch->effectiveSettings(), $applied?->amount ?? 0) + ['discount_label' => $applied?->label];
    }

    /** Kolom pesanan yang mencatat promo yang dipakai (snapshot nama, dan id untuk mengembalikan kuota). */
    private function discountColumns(?AppliedDiscount $applied): array
    {
        return ['discount_label' => $applied?->label, 'discount_id' => $applied?->discountId];
    }

    /** @return list<array{item_id:int,name:string,unit_price:int,qty:int,note:?string,modifiers:array}> */
    public function priceCart(int $branchId, array $cart): array
    {
        if ($cart === [] || count($cart) > config('onepay.max_lines')) {
            throw new OrderRejected('Keranjang kosong atau terlalu besar.');
        }
        $menu = collect($this->branchMenu($branchId))->keyBy('id');

        return array_map(fn ($entry) => $this->priceLine($menu->all(), (array) $entry), $cart);
    }

    private function priceLine(array $menu, array $entry): array
    {
        $item = $menu[(int) ($entry['item_id'] ?? 0)] ?? throw new OrderRejected('Ada menu yang tidak tersedia.');
        $qty = (int) ($entry['qty'] ?? 0);
        if ($qty < 1 || $qty > config('onepay.max_qty_per_line')) {
            throw new OrderRejected('Jumlah tidak valid.');
        }

        $chosen = $this->chosenModifiers($item, array_map('intval', (array) ($entry['modifier_ids'] ?? [])));
        $chosen = [...$chosen, ...$this->bundleComponentLines($item)];

        return [
            'item_id' => $item['id'],
            'name' => $item['name'],
            'unit_price' => max(0, $item['price'] + array_sum(array_column($chosen, 'price_delta'))),
            'qty' => $qty,
            'note' => mb_substr(trim((string) ($entry['note'] ?? '')), 0, 200) ?: null,
            'modifiers' => $chosen,
        ];
    }

    /** Isi paket dicatat sebagai baris tanpa harga supaya tampil di dapur dan struk. */
    private function bundleComponentLines(array $item): array
    {
        return array_map(
            fn ($component) => ['id' => null, 'name' => "Isi: {$component['qty']}× {$component['name']}", 'price_delta' => 0],
            $item['components'] ?? []
        );
    }

    /** Hanya opsi milik menu itu; grup wajib harus terpilih, satu pilihan per grup. */
    private function chosenModifiers(array $item, array $requestedIds): array
    {
        $chosen = [];
        foreach ($item['groups'] as $group) {
            $picked = array_values(array_filter($group['options'], fn ($o) => in_array($o['id'], $requestedIds, true)));
            if (count($picked) > 1 || ($group['required'] && $picked === [])) {
                throw new OrderRejected("Pilihan {$group['name']} untuk {$item['name']} belum benar.");
            }
            array_push($chosen, ...$picked);
        }
        return $chosen;
    }

    /**
     * Urutan: subtotal, diskon, service, pajak, pembulatan. Diskon mengurangi dasar service dan pajak.
     *
     * @return array{subtotal:int,discount:int,service:int,tax:int,rounding:int,total:int}
     */
    public function totals(array $lines, EffectiveSettings $settings, int $discount = 0): array
    {
        $subtotal = array_sum(array_map(fn ($line) => $line['unit_price'] * $line['qty'], $lines));
        $discount = min($discount, $subtotal);
        $discounted = $subtotal - $discount;
        $service = (int) round($discounted * $settings->servicePercent / 100);
        $tax = (int) round(($discounted + $service) * $settings->taxPercent / 100);
        $exact = $discounted + $service + $tax;

        $step = $settings->roundingTo;
        $total = (int) (round($exact / $step) * $step);

        return ['subtotal' => $subtotal, 'discount' => $discount, 'service' => $service, 'tax' => $tax, 'rounding' => $total - $exact, 'total' => $total];
    }

    private function assertMethodAllowed(string $method, int $total, EffectiveSettings $settings): void
    {
        $spec = PaymentMethods::find($method);
        if ($spec === null || !$settings->allowsMethod($method)) {
            throw new OrderRejected('Metode pembayaran tidak tersedia.');
        }
        if ($total < $spec['min'] || $total > $spec['max']) {
            throw new OrderRejected(sprintf('Total di luar batas %s (Rp %s sampai Rp %s).',
                $spec['label'], number_format($spec['min'], 0, ',', '.'), number_format($spec['max'], 0, ',', '.')));
        }
    }

    private function assertNotFlooding(string $clientIp): void
    {
        $pending = Order::query()
            ->where('client_ip', $clientIp)
            ->where('status', OrderStatus::PendingPayment)
            ->where('created_at', '>', now()->subMinutes(10))
            ->count();

        if ($pending >= config('onepay.max_pending_per_ip')) {
            throw new OrderRejected('Terlalu banyak pesanan belum dibayar. Selesaikan atau tunggu beberapa menit.');
        }
    }

    private function insertOrder(DiningTable $table, array $customer, array $totals, string $clientIp): Order
    {
        return Order::create([
            'code' => $this->newCode(),
            'branch_id' => $table->branch_id,
            'dining_table_id' => $table->id,
            'table_label' => $table->label,
            'status' => OrderStatus::PendingPayment,
            'customer_name' => mb_substr(trim((string) ($customer['name'] ?? '')), 0, 80) ?: null,
            'customer_phone' => mb_substr(trim((string) ($customer['phone'] ?? '')), 0, 30) ?: null,
            'expires_at' => now()->addMinutes((int) config('onepay.order_expiry_minutes')),
            'client_ip' => $clientIp,
        ] + $totals);
    }

    private function insertLines(Order $order, array $lines): void
    {
        $items = Item::query()->whereIn('id', array_column($lines, 'item_id'))->get()->keyBy('id');

        foreach ($lines as $line) {
            $item = $items[$line['item_id']];
            $orderItem = $order->items()->create([
                'item_id' => $item->id, 'name' => $line['name'], 'unit_price' => $line['unit_price'],
                'cost' => $item->cost, 'qty' => $line['qty'], 'station' => $item->station, 'note' => $line['note'],
            ]);
            foreach ($line['modifiers'] as $modifier) {
                $orderItem->modifiers()->create(['name' => $modifier['name'], 'price_delta' => $modifier['price_delta']]);
            }
        }
    }

    private function newCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $suffix = '';
        for ($i = 0; $i < 8; $i++) {
            $suffix .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        return 'OP' . now()->format('ymd') . $suffix;
    }
}
