<?php

use App\Services\Operations\Heartbeat;
use App\Services\PaymentService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('onepay:expire-orders', function (PaymentService $payments) {
    $this->info($payments->expireStaleOrders() . ' pesanan kedaluwarsa diproses.');
})->purpose('Ubah pesanan pending_payment yang lewat batas waktu menjadi expired');

// Di Hostinger: cron hPanel menjalankan `php artisan schedule:run` setiap menit.
Schedule::command('onepay:expire-orders')->everyMinute()->withoutOverlapping();

// Penanda scheduler hidup, dibaca onepay:doctor dan kartu Kesehatan sistem.
Schedule::call(fn () => app(Heartbeat::class)->beat())->name('onepay-heartbeat')->everyMinute();

// Backup database harian di luar jam ramai; salinan lama dihapus sesuai onepay.backup.keep_days.
Schedule::command('onepay:backup')->dailyAt('03:30')->withoutOverlapping();
