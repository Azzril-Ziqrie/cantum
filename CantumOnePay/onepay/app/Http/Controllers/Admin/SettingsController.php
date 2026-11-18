<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Brand;
use App\Services\GatewaySettings;
use App\Services\Kasera\PaymentMethods;
use App\Services\Operations\SystemHealth;
use App\Services\OrderRejected;
use App\Services\SettingsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    private const DAY_LABELS = ['mon' => 'Senin', 'tue' => 'Selasa', 'wed' => 'Rabu', 'thu' => 'Kamis', 'fri' => 'Jumat', 'sat' => 'Sabtu', 'sun' => 'Minggu'];

    public function __construct(private readonly SettingsService $settings)
    {
    }

    public function index(GatewaySettings $gateway, SystemHealth $health): View
    {
        $brand = Brand::current();

        return view('admin.settings', [
            'brand' => $brand,
            'branches' => Branch::query()->orderByDesc('is_active')->orderBy('name')->get(),
            'methods' => PaymentMethods::all(),
            'enabledMethods' => $brand->enabledMethods(),
            'dayLabels' => self::DAY_LABELS,
            'health' => $health->run(),
            'gateway' => [
                'mode' => $gateway->mode(),
                'configured' => $gateway->isConfigured(),
                'warning' => $gateway->modeWarning(),
                'api_key' => $gateway->maskedApiKey(),
                'webhook_secret' => $gateway->maskedWebhookSecret(),
                'webhook_url' => route('kasera.webhook'),
            ],
        ]);
    }

    public function updateBrand(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'tax_percent' => ['required', 'numeric', 'between:0,100'],
            'service_percent' => ['required', 'numeric', 'between:0,100'],
            'rounding_to' => ['required', 'integer', 'in:1,100,500,1000'],
            'enabled_methods' => ['required', 'array'],
            'enabled_methods.*' => ['string'],
            'accent_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'tagline' => ['nullable', 'string', 'max:160'],
        ]);

        return $this->run(fn () => $this->settings->updateBrand($request->user(), $data), 'Pengaturan brand disimpan.');
    }

    public function storeBranch(Request $request): RedirectResponse
    {
        return $this->run(fn () => $this->settings->saveBranch($request->user(), null, $this->validatedBranch($request)), 'Cabang ditambahkan.');
    }

    public function updateBranch(Request $request, Branch $branch): RedirectResponse
    {
        return $this->run(fn () => $this->settings->saveBranch($request->user(), $branch, $this->validatedBranch($request)), 'Cabang disimpan.');
    }

    public function updateGateway(Request $request): RedirectResponse
    {
        $data = $request->validate(['api_key' => ['nullable', 'string', 'max:200'], 'webhook_secret' => ['nullable', 'string', 'max:200']]);

        return $this->run(
            fn () => $this->settings->updateGateway($request->user(), $data['api_key'] ?? null, $data['webhook_secret'] ?? null),
            'Pengaturan gateway disimpan.'
        );
    }

    public function testGateway(Request $request): RedirectResponse
    {
        $result = $this->settings->testGateway($request->user());

        return back()->with($result['ok'] ? 'status' : 'gateway_error', $result['message']);
    }

    public function recordBackup(Request $request): RedirectResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:200']]);

        return $this->run(fn () => $this->settings->recordBackup($request->user(), $data['note'] ?? null), 'Backup dicatat.');
    }

    private function validatedBranch(Request $request): array
    {
        $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
            'prep_minutes' => ['required', 'integer', 'between:0,240'],
            'last_order_minutes' => ['required', 'integer', 'between:0,240'],
            'hours.*.open' => ['nullable', 'date_format:H:i'],
            'hours.*.close' => ['nullable', 'date_format:H:i'],
            'holidays' => ['nullable', 'string', 'max:2000'],
            'tax_percent' => ['nullable', 'numeric', 'between:0,100'],
            'service_percent' => ['nullable', 'numeric', 'between:0,100'],
            'rounding_to' => ['nullable', 'integer', 'in:1,100,500,1000'],
            'branch_methods' => ['nullable', 'array'],
            'branch_methods.*' => ['string'],
        ]);

        return $request->only('name', 'address', 'prep_minutes', 'last_order_minutes', 'hours', 'holidays', 'tax_percent', 'service_percent', 'rounding_to', 'branch_methods') + [
            'restrict_methods' => $request->boolean('restrict_methods'),
            'is_open' => $request->boolean('is_open'),
            'is_active' => $request->boolean('is_active'),
            'use_hours' => $request->boolean('use_hours'),
        ];
    }

    private function run(callable $action, string $success): RedirectResponse
    {
        try {
            $action();
        } catch (OrderRejected $rejection) {
            return back()->withErrors(['form' => $rejection->getMessage()])->withInput();
        }
        return back()->with('status', $success);
    }
}
