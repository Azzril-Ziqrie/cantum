<?php

namespace App\Enums;

enum PaymentOutcome
{
    case Paid;
    case Failed;

    /** Status transaksi dari GET /transactions/{id}. Sukses sebenarnya bernilai `succeeded`. */
    public static function fromGatewayStatus(string $status): ?self
    {
        return match (strtolower($status)) {
            'paid', 'succeeded', 'success', 'settlement', 'completed' => self::Paid,
            'expired', 'failed', 'cancelled', 'canceled', 'void' => self::Failed,
            default => null,
        };
    }

    public static function fromWebhookEvent(string $event): ?self
    {
        return match ($event) {
            'payment.paid' => self::Paid,
            'payment.expired', 'payment.failed' => self::Failed,
            default => null,
        };
    }
}
