<?php

namespace App\Enums;

enum Role: string
{
    case Owner = 'owner';
    case Manager = 'manager';
    case Cashier = 'cashier';
    case Kitchen = 'kitchen';
    case Warehouse = 'warehouse';

    /** Halaman pertama setelah masuk. Tiap role mendarat di halaman yang boleh dibukanya. */
    public function landingRoute(): string
    {
        return match ($this) {
            self::Kitchen => 'staff.kitchen',
            self::Warehouse => 'staff.warehouse',
            default => 'staff.dashboard',
        };
    }

    /** Modul stok belum ada; gudang hanya punya halaman penyambut sampai modul itu dirilis. */
    public function canViewWarehouse(): bool
    {
        return $this === self::Warehouse;
    }

    public function canManageOrders(): bool
    {
        return in_array($this, [self::Owner, self::Manager, self::Cashier, self::Kitchen], true);
    }

    /** Denah meja dan antrian lengkap. Dapur/bar hanya memakai layar stasiunnya. */
    public function canViewFloor(): bool
    {
        return in_array($this, [self::Owner, self::Manager, self::Cashier], true);
    }

    /** Kasir tidak membatalkan sendiri; ia mengajukan dan menunggu persetujuan. */
    public function canRequestCancellation(): bool
    {
        return $this === self::Cashier;
    }

    public function canCancelOrders(): bool
    {
        return in_array($this, [self::Owner, self::Manager], true);
    }

    /** Menu, meja, dan denah: owner dan manager (manager hanya cabangnya, dicek per aksi). */
    public function canManageMenu(): bool
    {
        return in_array($this, [self::Owner, self::Manager], true);
    }

    /** Ringkasan semua cabang: hanya owner. */
    public function canViewOverview(): bool
    {
        return $this === self::Owner;
    }

    public function canViewReports(): bool
    {
        return in_array($this, [self::Owner, self::Manager], true);
    }

    /** Gateway, pajak, pengguna, dan pengaturan brand: hanya owner. */
    public function canManageSettings(): bool
    {
        return $this === self::Owner;
    }

    public function canToggleAvailability(): bool
    {
        return in_array($this, [self::Owner, self::Manager, self::Kitchen], true);
    }

    public function canOpenCloseBranch(): bool
    {
        return in_array($this, [self::Owner, self::Manager], true);
    }
}
