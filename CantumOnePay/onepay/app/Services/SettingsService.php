<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\User;
use App\Services\Kasera\KaseraClient;
use App\Services\Kasera\KaseraException;
use App\Services\Kasera\PaymentMethods;

/** Pengaturan brand, cabang, gateway, dan catatan backup. Hanya owner. */
class SettingsService
{
    public function __construct(private readonly GatewaySettings $gateway, private readonly KaseraClient $kasera)
    {
    }

    public function updateBrand(User $actor, array $data): Brand
    {
        $this->assertOwner($actor);

        $methods = array_values(array_intersect(array_keys(PaymentMethods::all()), (array) ($data['enabled_methods'] ?? [])));
        if ($methods === []) {
            throw new OrderRejected('Aktifkan minimal satu metode pembayaran.');
        }

        $brand = Brand::current();
        $before = $brand->only('tax_percent', 'service_percent', 'rounding_to', 'enabled_methods');
        $brand->update([
            'name' => $data['name'],
            'tax_percent' => $data['tax_percent'],
            'service_percent' => $data['service_percent'],
            'rounding_to' => $data['rounding_to'],
            'enabled_methods' => implode(',', $methods),
            'accent_color' => $data['accent_color'],
            'tagline' => $data['tagline'] ?? null,
        ]);
        AuditLog::record($actor, 'brand.update', 'brand', $brand->id, json_encode($before), json_encode($brand->only('tax_percent', 'service_percent', 'rounding_to', 'enabled_methods')));
        return $brand;
    }

    public function saveBranch(User $actor, ?Branch $branch, array $data): Branch
    {
        $this->assertOwner($actor);

        $attributes = [
            'name' => $data['name'],
            'address' => $data['address'] ?? null,
            'is_open' => (bool) ($data['is_open'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'prep_minutes' => (int) ($data['prep_minutes'] ?? 15),
            'last_order_minutes' => (int) ($data['last_order_minutes'] ?? 0),
            'open_hours' => !empty($data['use_hours']) ? $this->normalizeHours((array) ($data['hours'] ?? [])) : null,
            'holidays' => $this->parseHolidays((string) ($data['holidays'] ?? '')),
        ];

        $rules = $this->ruleOverrides($data);
        $before = $branch?->only(self::RULE_KEYS);

        $branch === null ? $branch = Branch::create($attributes + $rules) : $branch->update($attributes + $rules);
        AuditLog::record($actor, 'branch.save', 'branch', $branch->id, null, $branch->name);
        $this->auditRules($actor, $branch, $before);
        return $branch;
    }

    private const RULE_KEYS = ['tax_percent', 'service_percent', 'rounding_to', 'enabled_methods'];

    /** Aturan khusus cabang. Kosong = null = ikut brand. Metode cabang harus bagian dari metode aktif brand. */
    private function ruleOverrides(array $data): array
    {
        $blankToNull = fn ($value) => ($value === null || $value === '') ? null : $value;

        return [
            'tax_percent' => $blankToNull($data['tax_percent'] ?? null),
            'service_percent' => $blankToNull($data['service_percent'] ?? null),
            'rounding_to' => $blankToNull($data['rounding_to'] ?? null),
            'enabled_methods' => $this->branchMethodsOverride($data),
        ];
    }

    private function branchMethodsOverride(array $data): ?string
    {
        if (empty($data['restrict_methods'])) {
            return null;
        }
        $allowed = Brand::current()->enabledMethods();
        $chosen = array_values(array_intersect(array_keys(PaymentMethods::all()), (array) ($data['branch_methods'] ?? [])));
        if ($chosen === []) {
            throw new OrderRejected('Pilih minimal satu metode pembayaran untuk cabang ini, atau matikan pembatasan.');
        }
        if (array_diff($chosen, $allowed) !== []) {
            throw new OrderRejected('Metode cabang harus termasuk metode yang aktif di brand.');
        }
        return implode(',', $chosen);
    }

    private function auditRules(User $actor, Branch $branch, ?array $before): void
    {
        $after = $branch->only(self::RULE_KEYS);
        if ($before !== $after && ($before !== null || array_filter($after, fn ($v) => $v !== null) !== [])) {
            AuditLog::record($actor, 'branch.rules', 'branch', $branch->id, json_encode($before), json_encode($after));
        }
    }

    /** Kunci kosong = pertahankan yang sekarang. Nilai tidak pernah dicatat di audit. */
    public function updateGateway(User $actor, ?string $apiKey, ?string $webhookSecret): void
    {
        $this->assertOwner($actor);
        if ($apiKey !== null && $apiKey !== '' && !preg_match('/^kp_(test|live)_[A-Za-z0-9_\-]+$/', $apiKey)) {
            throw new OrderRejected('Kunci API harus berawalan kp_test_ (sandbox) atau kp_live_ (produksi).');
        }
        $this->gateway->save($apiKey, $webhookSecret);
        AuditLog::record($actor, 'gateway.update', 'gateway', 1, null, $this->gateway->mode());
    }

    /** @return array{ok:bool,message:string} */
    public function testGateway(User $actor): array
    {
        $this->assertOwner($actor);
        if ($this->gateway->apiKey() === '') {
            return ['ok' => false, 'message' => 'Kunci API belum diisi.'];
        }
        try {
            $this->kasera->listPaymentMethods();
            return ['ok' => true, 'message' => 'Terhubung ke Kasera (' . $this->gateway->mode() . ').'];
        } catch (KaseraException $error) {
            return ['ok' => false, 'message' => $error->getMessage()];
        }
    }

    public function recordBackup(User $actor, ?string $note): void
    {
        $this->assertOwner($actor);
        Brand::current()->update(['last_backup_at' => now(), 'backup_note' => $note]);
        AuditLog::record($actor, 'backup.record', 'brand', 1, null, $note);
    }

    /** @return array<string, array{closed:bool,open:string,close:string}> */
    private function normalizeHours(array $input): array
    {
        $hours = [];
        foreach (BranchSchedule::DAYS as $day) {
            $row = (array) ($input[$day] ?? []);
            $hours[$day] = [
                'closed' => !empty($row['closed']),
                'open' => $this->validTime($row['open'] ?? null, '08:00'),
                'close' => $this->validTime($row['close'] ?? null, '22:00'),
            ];
        }
        return $hours;
    }

    private function validTime(?string $value, string $default): string
    {
        return $value !== null && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value) ? $value : $default;
    }

    /** Satu tanggal (Y-m-d) per baris; baris tidak valid diabaikan. */
    private function parseHolidays(string $text): ?array
    {
        $dates = [];
        foreach (preg_split('/[\s,]+/', trim($text)) ?: [] as $candidate) {
            $date = \DateTime::createFromFormat('Y-m-d', $candidate);
            if ($date && $date->format('Y-m-d') === $candidate) {
                $dates[$candidate] = $candidate;
            }
        }
        ksort($dates);
        return $dates === [] ? null : array_values($dates);
    }

    private function assertOwner(User $actor): void
    {
        if (!$actor->role->canManageSettings()) {
            throw new OrderRejected('Hanya owner yang boleh mengubah pengaturan.');
        }
    }
}
