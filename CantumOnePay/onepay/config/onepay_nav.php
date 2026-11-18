<?php

/**
 * Menu sidebar staf. Setiap modul menambah satu baris dengan nama route-nya.
 * Item hanya tampil bila route-nya sudah ada dan role pengguna punya izin (nama method di App\Enums\Role).
 * Format: [route, label, path ikon SVG (24x24), izin Role]
 */
return [
    ['staff.dashboard', 'Denah meja', 'M4 5h7v7H4zM13 5h7v4h-7zM13 11h7v8h-7zM4 14h7v5H4z', 'canViewFloor'],
    ['staff.overview', 'Semua cabang', 'M4 20V9l8-5 8 5v11zM9 20v-6h6v6', 'canViewOverview'],
    ['staff.kitchen', 'Dapur dan bar', 'M6 3v8a3 3 0 0 0 3 3v7M9 3v6M12 3v8a3 3 0 0 1-3 3M17 3c-2 2-2 6 0 8v10', 'canManageOrders'],
    ['staff.warehouse', 'Gudang', 'M3 9l9-5 9 5v11H3zM8 20v-7h8v7', 'canViewWarehouse'],
    ['staff.refunds', 'Refund', 'M4 12a8 8 0 1 0 3-6.2M4 4v4h4M12 8v4l3 2', 'canCancelOrders'],
    ['admin.tables', 'Meja dan denah', 'M5 5h14v14H5zM9 5v14M5 12h14', 'canManageMenu'],
    ['admin.menu', 'Menu', 'M5 4h14v16H5zM8 9h8M8 13h8M8 17h5', 'canManageMenu'],
    ['admin.discounts', 'Diskon dan promo', 'M5 19L19 5M7.5 8.5a1.5 1.5 0 1 0 0-.01M16.5 17.5a1.5 1.5 0 1 0 0-.01', 'canManageMenu'],
    ['admin.reports', 'Laporan', 'M5 20V10M12 20V4M19 20v-7', 'canViewReports'],
    ['admin.users', 'Pengguna', 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM4 20c0-4 3.5-6 8-6s8 2 8 6', 'canManageSettings'],
    ['admin.settings', 'Pengaturan', 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM19 12l2 1-2 3-2-1a7 7 0 0 1-2 1l-.5 2h-3L11 16a7 7 0 0 1-2-1l-2 1-2-3 2-1a7 7 0 0 1 0-2L5 9l2-3 2 1a7 7 0 0 1 2-1l.5-2h3L15 6a7 7 0 0 1 2 1l2-1 2 3-2 1a7 7 0 0 1 0 2z', 'canManageSettings'],
];
