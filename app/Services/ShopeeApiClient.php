<?php

namespace App\Services;

use App\Models\ShopeeApiConnection;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\RequestException;
use Throwable;

class ShopeeApiClient
{
    public const PATH_ORDER_LIST = '/api/v2/order/get_order_list';

    public const PATH_ORDER_DETAIL = '/api/v2/order/get_order_detail';

    public const PATH_ESCROW_DETAIL = '/api/v2/payment/get_escrow_detail';

    public const PATH_INCOME_DETAIL = '/api/v2/payment/get_income_detail';

    public function __construct(
        private readonly HttpFactory $http,
        private readonly array $config = [],
    ) {}

    public static function fromConfig(): self
    {
        return new self(app(HttpFactory::class), (array) config('shopee-api'));
    }

    public static function fromConnection(ShopeeApiConnection $connection): self
    {
        return new self(app(HttpFactory::class), [
            'environment' => $connection->environment,
            'region' => $connection->region,
            'host' => '',
            'timeout' => (int) config('shopee-api.timeout', 30),
            'partner_id' => $connection->partner_id,
            'partner_key' => $connection->partner_key,
            'shop_id' => $connection->shop_id,
            'access_token' => $connection->access_token,
        ]);
    }

    public function isConfigured(): bool
    {
        return filled($this->config['partner_id'] ?? null)
            && filled($this->config['partner_key'] ?? null)
            && filled($this->config['shop_id'] ?? null)
            && filled($this->config['access_token'] ?? null);
    }

    public function missing(): array
    {
        $required = ['partner_id', 'partner_key', 'shop_id', 'access_token'];

        return array_values(array_filter($required, fn (string $key): bool => empty($this->config[$key] ?? null)));
    }

    public function connectionStatus(): array
    {
        return [
            'configured' => $this->isConfigured(),
            'missing' => $this->missing(),
            'environment' => $this->config['environment'] ?? 'production',
            'region' => $this->config['region'] ?? 'global',
            'host' => $this->host(),
        ];
    }

    public function host(): string
    {
        if (filled($this->config['host'] ?? null)) {
            return rtrim((string) $this->config['host'], '/');
        }

        return ($this->config['environment'] ?? 'production') === 'sandbox'
            ? 'https://openplatform.sandbox.test-stable.shopee.sg'
            : 'https://partner.shopeemobile.com';
    }

    public function testConnection(): array
    {
        if (! $this->isConfigured()) {
            return [
                'ok' => false,
                'configured' => false,
                'missing' => $this->missing(),
                'error' => 'Shopee API credentials are not configured.',
                'rate_limited' => false,
            ];
        }

        try {
            $envelope = $this->request(self::PATH_ORDER_LIST, [
                'page_size' => 5,
                'time_range_field' => 'create_time',
                'time_from' => now()->subDays(7)->timestamp,
                'time_to' => now()->timestamp,
            ]);

            $ok = ($envelope['error'] ?? null) === '-';

            return [
                'ok' => $ok,
                'configured' => true,
                'error' => $ok ? null : ($envelope['message'] ?? 'Unknown Shopee API error.'),
                'message' => $ok ? 'Connected to Shopee Open API.' : 'Shopee API call failed.',
                'rate_limited' => false,
                'raw' => $envelope,
            ];
        } catch (ShopeeApiException $exception) {
            return [
                'ok' => false,
                'configured' => true,
                'error' => $exception->getMessage(),
                'rate_limited' => $exception->isRateLimited(),
            ];
        }
    }

    public function getOrderList(array $params = []): array
    {
        return $this->request(self::PATH_ORDER_LIST, $params);
    }

    public function getOrderDetail(string $orderSn): array
    {
        return $this->request(self::PATH_ORDER_DETAIL, ['order_sn_list' => $orderSn]);
    }

    public function getEscrowDetail(string $orderSn): array
    {
        return $this->request(self::PATH_ESCROW_DETAIL, ['order_sn' => $orderSn]);
    }

    public function getIncomeDetail(array $params = []): array
    {
        return $this->request(self::PATH_INCOME_DETAIL, $params);
    }

    private function request(string $path, array $params = []): array
    {
        if (! $this->isConfigured()) {
            throw ShopeeApiException::notConfigured();
        }

        $timestamp = now()->timestamp;

        $query = array_merge($params, $this->authParams($path, $timestamp));

        try {
            $response = $this->http
                ->asJson()
                ->timeout((int) ($this->config['timeout'] ?? 30))
                ->acceptJson()
                ->get($this->host().$path, $query)
                ->throw();
        } catch (RequestException $exception) {
            throw ShopeeApiException::http($exception->response?->status() ?? 502, $exception->response?->body());
        } catch (Throwable $exception) {
            throw new ShopeeApiException('Shopee API request failed: '.$exception->getMessage(), status: 502);
        }

        $payload = json_decode((string) $response->getBody(), true);

        if (! is_array($payload)) {
            throw new ShopeeApiException('Invalid Shopee API response payload.', status: 502);
        }

        $error = $payload['error'] ?? null;

        if (is_string($error) && $error !== '' && $error !== '-') {
            $message = (string) ($payload['message'] ?? 'Shopee API error: '.$error);
            $rateLimited = str_contains(strtolower($error.' '.$message), 'rate')
                || str_contains(strtolower($error.' '.$message), 'too many');

            throw new ShopeeApiException($message, errorCode: $error, rateLimited: $rateLimited);
        }

        return $payload;
    }

    private function authParams(string $path, int $timestamp): array
    {
        return [
            'partner_id' => (string) $this->config['partner_id'],
            'access_token' => (string) $this->config['access_token'],
            'shop_id' => (string) $this->config['shop_id'],
            'timestamp' => $timestamp,
            'sign' => $this->sign($path, $timestamp),
        ];
    }

    private function sign(string $path, int $timestamp): string
    {
        $baseString = (string) $this->config['partner_id']
            .$path
            .$timestamp
            .(string) $this->config['access_token']
            .(string) $this->config['shop_id'];

        return hash_hmac('sha256', $baseString, (string) $this->config['partner_key']);
    }
}
