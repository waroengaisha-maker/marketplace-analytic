<?php

namespace App\Services;

use App\Models\ShopeeApiConnection;
use Illuminate\Http\Client\Factory as HttpFactory;

class ShopeeApiClientFactory
{
    public function __construct(
        private readonly HttpFactory $http,
    ) {}

    public function fromConnection(ShopeeApiConnection $connection): ShopeeApiClient
    {
        return new ShopeeApiClient($this->http, [
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
}
