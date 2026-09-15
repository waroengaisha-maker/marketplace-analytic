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
            ? 'https://openplatform.sandbox.test-stable.shopee.sg'
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

        $timestamp ??= now()->timestamp;
        $endpoint = $this->host($connection).self::PATH_AUTH;
        $redirect = route('integrations.shopee-api.shopee-auth');

        if ($state !== null && $state !== '') {
            $redirect .= (str_contains($redirect, '?') ? '&' : '?').'state='.rawurlencode($state);
        }

        $baseString = (string) $connection->partner_id.$endpoint.$timestamp;
        $sign = hash_hmac('sha256', $baseString, (string) $connection->partner_key);

        return $endpoint
            .'?partner_id='.rawurlencode((string) $connection->partner_id)
            .'&redirect='.rawurlencode($redirect)
            .'&token='.$timestamp
            .'&sign='.$sign;
    }

    /**
     * Exchanges the one-time authorization code for access/refresh tokens.
     * Signature: HMAC(partner_id . path . timestamp . code).
     *
     * @return array<string, mixed>
     */
    public function exchangeCode(ShopeeApiConnection $connection, string $code, ?int $timestamp = null): array
    {
        $timestamp ??= now()->timestamp;
        $baseString = (string) $connection->partner_id.self::PATH_TOKEN_GET.$timestamp.$code;

        $params = [
            'partner_id' => (string) $connection->partner_id,
            'code' => $code,
            'timestamp' => $timestamp,
            'sign' => hash_hmac('sha256', $baseString, (string) $connection->partner_key),
        ];

        if (filled($connection->shop_id)) {
            $params['shop_id'] = (string) $connection->shop_id;
        }

        return $this->authPost($connection, self::PATH_TOKEN_GET, $params);
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
            .$timestamp
            .(string) $connection->refresh_token
            .(string) $connection->shop_id;

        return $this->authPost($connection, self::PATH_ACCESS_TOKEN_GET, [
            'partner_id' => (string) $connection->partner_id,
            'shop_id' => (string) $connection->shop_id,
            'refresh_token' => (string) $connection->refresh_token,
            'timestamp' => $timestamp,
            'sign' => hash_hmac('sha256', $baseString, (string) $connection->partner_key),
        ]);
    }

    /**
     * @param  array<string, mixed>  $form
     * @return array<string, mixed>
     */
    private function authPost(ShopeeApiConnection $connection, string $path, array $form): array
    {
        try {
            $response = $this->http
                ->asForm()
                ->timeout((int) config('shopee-api.timeout', 30))
                ->acceptJson()
                ->post($this->host($connection).$path, $form)
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
