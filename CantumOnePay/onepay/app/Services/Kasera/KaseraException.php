<?php

namespace App\Services\Kasera;

use RuntimeException;

class KaseraException extends RuntimeException
{
    /** Kode galat Kasera yang artinya "permintaan sama sudah pernah dibuat" atau Idempotency-Key bentrok. */
    private const CONFLICT_STATUSES = [409, 422];

    public function __construct(string $message, public readonly int $httpStatus = 0, public readonly array $body = [])
    {
        parent::__construct($message);
    }

    public function isConflict(): bool
    {
        return in_array($this->httpStatus, self::CONFLICT_STATUSES, true);
    }

    /** Id tagihan yang sudah ada, bila Kasera menyebutkannya di badan galat. */
    public function existingTransactionId(): ?string
    {
        $candidates = [
            $this->body['id'] ?? null,
            $this->body['data']['id'] ?? null,
            $this->body['existing']['id'] ?? null,
            $this->body['error']['existing_id'] ?? null,
            $this->body['payment_request_id'] ?? null,
        ];
        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                return $candidate;
            }
        }
        return null;
    }

    /** Kode galat mesin (mis. delivery_date_required) bila ada. */
    public function errorCode(): ?string
    {
        $code = $this->body['error']['code'] ?? $this->body['code'] ?? null;
        return is_string($code) ? $code : null;
    }
}
