<?php

return [
    'order_expiry_minutes' => (int) env('ONEPAY_ORDER_EXPIRY_MINUTES', 15),

    // Pembatasan pesanan belum dibayar per IP (QR bisa difoto dan dipakai dari jauh).
    'max_pending_per_ip' => 5,
    'max_qty_per_line'   => 50,
    'max_lines'          => 40,

    'env_path' => base_path('.env'),

    // Penanda scheduler hidup (ditulis tiap menit oleh schedule:run, dibaca onepay:doctor).
    'heartbeat_path' => storage_path('app/scheduler-heartbeat'),

    'backup' => [
        'path'            => storage_path('app/backups'),
        'keep_days'       => (int) env('ONEPAY_BACKUP_KEEP_DAYS', 14),
        'use_mysqldump'   => (bool) env('ONEPAY_BACKUP_MYSQLDUMP', true),
        'mysqldump_path'  => env('ONEPAY_MYSQLDUMP_PATH'),
        'stale_after_hours' => 48,
    ],

    'kasera' => [
        'api_base_url'      => env('KASERA_API_BASE_URL', 'https://pay.kasera.id/v1'),
        'api_key'           => env('KASERA_API_KEY'),
        'webhook_secret'    => env('KASERA_WEBHOOK_SECRET'),
        'webhook_tolerance' => 300,
        'sync_interval'     => 10,
        'timeout'           => 15,
        'connect_timeout'   => 8,
        // Jeda sebelum tiap percobaan ulang untuk error jaringan/5xx (ms). Panjang array = jumlah retry.
        'retry_backoff_ms'  => [300, 900],
        // Kasera mewajibkan delivery_date dan delivery_terms_shown untuk pembayaran di atas ambang ini.
        'delivery_terms'    => (bool) env('KASERA_DELIVERY_TERMS', true),
        'delivery_threshold' => 1_000_000,
        'delivery_terms_text' => 'Ketentuan layanan: makanan dan minuman pesanan ini disajikan hari ini di mejamu. Pesanan tidak dapat dijadwalkan ulang atau dijadikan pesanan antar.',
    ],
];
