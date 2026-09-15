<?php

namespace App\Services;

use RuntimeException;

class ShopeeApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?string $errorCode = null,
        public readonly ?int $status = null,
        public readonly bool $rateLimited = false,
    ) {
        parent::__construct($message);
    }

    public function isRateLimited(): bool
    {
        return $this->rateLimited;
    }

    public function httpStatus(): ?int
    {
        return $this->status;
    }

    public static function notConfigured(): self
    {
        return new self('Shopee API credentials are not configured.', status: 422);
    }

    public static function http(int $status, ?string $body): self
    {
        $rateLimited = $status === 429;

        return new self(
            'Shopee API HTTP '.$status.($rateLimited ? ' (rate limited).' : '.'),
            status: $status,
            rateLimited: $rateLimited,
        );
    }
}
