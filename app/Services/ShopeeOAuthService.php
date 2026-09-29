<?php

namespace App\Services;

use App\Models\ShopeeApiConnection;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\RequestException;
use Throwable;

/**
 * Shopee Open Platform v2 partner authorization + token management.
 *
 * Live verification requires sandbox credentials and a browser authorization
 * round-trip, so every algorithm here is centralized and covered by tests
 * against faked HTTP. No token or credential values are ever logged.
 */
class ShopeeOAuthService
{
    public const PATH_AUTH = '/api/v2/shop/auth_partner';

    public const PATH_TOKEN_GET = '/api/v2/auth/token/get';

    public const PATH_ACCESS_TOKEN_GET = '/api/v2/auth/access_token/get';

    public function __construct(
        private readonly HttpFactory $http,
    ) {}

    public function host(ShopeeApiConnection $connection): string
    {
        return ($connection->environment ?? 'production') === 'sandbox'
            ? 'https://partner.test-stable.shopeemobile.com'
            : 'https://partner.shopeemobile.com';
    }

    /**
     * Builds the signed authorization URL the merchant opens in a browser.
     * Signature per Shopee docs: HMAC(partner_id . authUrl . timestamp).
     *
     * When $state is given it is appended to the redirect URL (Shopee preserves
     * existing redirect query params and appends code/shop_id), so the callback
     * can verify the round-trip came from this app.
     */
    public function authorizationUrl(ShopeeApiConnection $connection, ?int $timestamp = null, ?string $state = null): string
    {
        if (blank($connection->partner_id) || blank($connection->partner_key)) {
            throw ShopeeApiException::notConfigured();
        }

        $redirect = route('integrations.shopee-api.shopee-auth');

        if ($state !== null && $state !== '') {
            $redirect .= (str_contains($redirect, '?') ? '&' : '?').'state='.rawurlencode($state);
        }

        // Sandbox V2 uses the dedicated seller authorization portal. The
        // legacy /api/v2/shop/auth_partner endpoint can reject otherwise
        // correctly signed Sandbox V2 credentials with error_sign.
        if (($connection->environment ?? 'production') === 'sandbox') {
            return 'https://open.sandbox.test-stable.shopee.com/auth'
                .'?auth_type=seller'
                .'&partner_id='.rawurlencode((string) $connection->partner_id)
                .'&redirect_uri='.rawurlencode($redirect)
                .'&response_type=code';
        }

        $timestamp ??= now()->timestamp;
        $endpoint = $this->host($connection).self::PATH_AUTH;
        $baseString = (string) $connection->partner_id.self::PATH_AUTH.$timestamp;
        $sign = hash_hmac('sha256', $baseString, trim((string) $connection->partner_key));

        return $endpoint
            .'?partner_id='.rawurlencode((string) $connection->partner_id)
            .'&redirect='.rawurlencode($redirect)
            .'&timestamp='.$timestamp
            .'&sign='.$sign;
    }

    /**
     * Exchanges the one-time authorization code for access/refresh tokens.
     * Signature: HMAC(partner_id . path . timestamp . code).
     *
     * @return array<string, mixed>
     */
    public function exchangeCode(
        ShopeeApiConnection $connection,
        string $code,
        ?int $timestamp = null,
        ?string $shopId = null,
    ): array {
        $timestamp ??= now()->timestamp;

        // Shopee Public API signing uses partner_id + path + timestamp.
        // The signed values belong in the query string; the OAuth payload
        // itself is JSON.
        $baseString = (string) $connection->partner_id
            .self::PATH_TOKEN_GET
            .$timestamp;

        $query = [
            'partner_id' => (string) $connection->partner_id,
            'timestamp' => $timestamp,
            'sign' => hash_hmac(
                'sha256',
                $baseString,
                trim((string) $connection->partner_key)
            ),
        ];

        $body = [
            'code' => $code,
            'partner_id' => (int) $connection->partner_id,
        ];

        $resolvedShopId = $shopId ?: $connection->shop_id;

        if (filled($resolvedShopId)) {
            $body['shop_id'] = (int) $resolvedShopId;
        }

        return $this->authPost(
            $connection,
            self::PATH_TOKEN_GET,
            $query,
            $body,
        );
    }

    /**
     * Refreshes the short-lived access token.
     * Signature: HMAC(partner_id . path . timestamp . refresh_token . shop_id).
     *
     * @return array<string, mixed>
     */
    public function refreshAccessToken(ShopeeApiConnection $connection, ?int $timestamp = null): array
    {
        $timestamp ??= now()->timestamp;

        $baseString = (string) $connection->partner_id
            .self::PATH_ACCESS_TOKEN_GET
            .$timestamp;

        return $this->authPost(
            $connection,
            self::PATH_ACCESS_TOKEN_GET,
            [
                'partner_id' => (string) $connection->partner_id,
                'timestamp' => $timestamp,
                'sign' => hash_hmac(
                    'sha256',
                    $baseString,
                    trim((string) $connection->partner_key)
                ),
            ],
            [
                'refresh_token' => (string) $connection->refresh_token,
                'shop_id' => (int) $connection->shop_id,
                'partner_id' => (int) $connection->partner_id,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $form
     * @return array<string, mixed>
     */
    private function authPost(
        ShopeeApiConnection $connection,
        string $path,
        array $query,
        array $body,
    ): array {
        try {
            $response = $this->http
                ->asJson()
                ->timeout((int) config('shopee-api.timeout', 30))
                ->acceptJson()
                ->withOptions([
                    'query' => $query,
                ])
                ->post(
                    $this->host($connection).$path,
                    $body,
                )
                ->throw();
        } catch (RequestException $exception) {
            throw ShopeeApiException::http($exception->response?->status() ?? 502, $exception->response?->body());
        } catch (Throwable $exception) {
            throw new ShopeeApiException('Shopee auth request failed: '.$exception->getMessage(), status: 502);
        }

        $payload = json_decode((string) $response->getBody(), true);

        if (! is_array($payload)) {
            throw new ShopeeApiException('Invalid Shopee auth response payload.', status: 502);
        }

        $error = $payload['error'] ?? null;

        if (is_string($error) && $error !== '' && $error !== '-') {
            $message = (string) ($payload['message'] ?? 'Shopee auth error: '.$error);
            $rateLimited = str_contains(strtolower($error.' '.$message), 'rate')
                || str_contains(strtolower($error.' '.$message), 'too many');

            throw new ShopeeApiException($message, errorCode: $error, rateLimited: $rateLimited);
        }

        return $payload;
    }
}
