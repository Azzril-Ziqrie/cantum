<?php

namespace App\Providers;

use App\Services\Kasera\WebhookSignature;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(WebhookSignature::class, fn () => WebhookSignature::fromConfig());
    }

    public function boot(): void
    {
        $this->hardenForProduction();
        $this->followAppUrlScheme();
    }

    /** Di produksi, halaman galat tidak boleh membocorkan jejak kode, .env, atau kunci, apa pun isi APP_DEBUG. */
    private function hardenForProduction(): void
    {
        if ($this->app->isProduction()) {
            config(['app.debug' => false]);
            URL::forceRootUrl((string) config('app.url')); // tautan selalu memakai APP_URL, bukan header Host dari request
        }
    }

    /** APP_URL berskema https: tautan dipaksa https dan cookie sesi ditandai secure. */
    private function followAppUrlScheme(): void
    {
        if (!str_starts_with((string) config('app.url'), 'https://')) {
            return;
        }
        URL::forceScheme('https');
        config(['session.secure' => config('session.secure') ?? true]);
    }
}
