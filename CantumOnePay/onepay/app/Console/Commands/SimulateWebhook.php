<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/** Uji webhook tanpa Kasera: menandatangani body dengan webhook_secret lalu mengirimnya ke aplikasi ini. */
class SimulateWebhook extends Command
{
    protected $signature = 'onepay:simulate-webhook {code : Kode pesanan} {event=payment.paid} {--twice : Kirim dua kali (uji idempotensi)}';

    protected $description = 'Kirim webhook Kasera palsu bertanda tangan ke aplikasi ini (hanya untuk pengembangan)';

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('Tidak tersedia di produksi.');
            return self::FAILURE;
        }

        $body = json_encode(['event' => $this->argument('event'), 'data' => ['id' => 'sim_' . $this->argument('code'), 'external_id' => $this->argument('code'), 'status' => 'succeeded']]);
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp . '.' . $body, (string) config('onepay.kasera.webhook_secret'));

        foreach (range(1, $this->option('twice') ? 2 : 1) as $_) {
            $response = Http::withBody($body, 'application/json')
                ->withHeaders(['Kasera-Signature-V1' => "t=$timestamp,v1=$signature"])
                ->post(route('kasera.webhook'));
            $this->line('HTTP ' . $response->status() . ' ' . $response->body());
        }
        return self::SUCCESS;
    }
}
