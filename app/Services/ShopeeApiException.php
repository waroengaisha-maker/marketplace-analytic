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

        $message = 'Shopee API HTTP '.$status.($rateLimited ? ' (rate limited).' : '.');

        // Expose only Shopee's non-secret diagnostic fields. Never include
        // request bodies, OAuth codes, access tokens, refresh tokens, or keys.
        if (filled($body)) {
            $payload = json_decode($body, true);

            if (is_array($payload)) {
                $error = $payload['error'] ?? null;
                $remoteMessage = $payload['message'] ?? null;
                $requestId = $payload['request_id'] ?? null;

                if (filled($error)) {
                    $message .= ' error='.$error.'.';
                }

                if (filled($remoteMessage)) {
                    $message .= ' message='.$remoteMessage.'.';
                }

                if (filled($requestId)) {
                    $message .= ' request_id='.$requestId.'.';
                }
            }
        }

        return new self(
            $message,
            status: $status,
            rateLimited: $rateLimited,
        );
    }
}
