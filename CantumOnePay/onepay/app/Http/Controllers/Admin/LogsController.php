<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\PaymentWebhook;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/** Log audit dan log webhook sebagai halaman daftar dengan filter. */
class LogsController extends Controller
{
    private const PER_PAGE = 50;

    public function audit(Request $request): View
    {
        $filters = $request->validate([
            'user_id' => ['nullable', 'integer'], 'action' => ['nullable', 'string', 'max:60'],
            'from' => ['nullable', 'date'], 'to' => ['nullable', 'date'],
        ]);

        $logs = AuditLog::query()->with('user:id,name')
            ->when($filters['user_id'] ?? null, fn ($q, $id) => $q->where('user_id', $id))
            ->when($filters['action'] ?? null, fn ($q, $action) => $q->where('action', 'like', $this->like($action)))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->where('created_at', '>=', $from . ' 00:00:00'))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->where('created_at', '<=', $to . ' 23:59:59'))
            ->orderByDesc('id')->paginate(self::PER_PAGE)->withQueryString();

        return view('admin.audit', [
            'brand' => Brand::current(), 'logs' => $logs, 'filters' => $filters,
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function webhooks(Request $request): View
    {
        $filters = $request->validate([
            'result' => ['nullable', 'string', 'max:80'], 'signature' => ['nullable', 'in:valid,invalid'],
            'code' => ['nullable', 'string', 'max:20'], 'from' => ['nullable', 'date'], 'to' => ['nullable', 'date'],
        ]);

        $logs = PaymentWebhook::query()
            ->when($filters['result'] ?? null, fn ($q, $result) => $q->where('result', $result))
            ->when($filters['signature'] ?? null, fn ($q, $signature) => $q->where('signature_valid', $signature === 'valid'))
            ->when($filters['code'] ?? null, fn ($q, $code) => $q->where('order_code', $code))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->where('received_at', '>=', $from . ' 00:00:00'))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->where('received_at', '<=', $to . ' 23:59:59'))
            ->orderByDesc('id')->paginate(self::PER_PAGE)->withQueryString();

        return view('admin.webhooks', ['brand' => Brand::current(), 'logs' => $logs, 'filters' => $filters]);
    }

    /** Karakter wildcard dari input pengguna dinetralkan. */
    private function like(string $text): string
    {
        return '%' . addcslashes($text, '%_\\') . '%';
    }
}
