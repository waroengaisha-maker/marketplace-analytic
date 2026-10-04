<?php

return [
    'domain' => env('SERVICES_HUB_DOMAIN', 'marketplace-analytics.my.id'),

    'services' => [
        [
            'key' => 'netdata',
            'name' => 'Netdata',
            'description' => 'Host and Docker monitoring.',
            'url' => 'https://netdata.marketplace-analytics.my.id',
            'icon' => 'pi pi-chart-line',
            'risk' => 'medium',
        ],
        [
            'key' => 'portainer',
            'name' => 'Portainer',
            'description' => 'Docker management and container operations.',
            'url' => 'https://portainer.marketplace-analytics.my.id',
            'icon' => 'pi pi-box',
            'risk' => 'critical',
        ],
        [
            'key' => 'adminer',
            'name' => 'Adminer',
            'description' => 'MySQL database administration.',
            'url' => 'https://adminer.marketplace-analytics.my.id',
            'icon' => 'pi pi-database',
            'risk' => 'high',
        ],
        [
            'key' => 'redisinsight',
            'name' => 'Redis Insight',
            'description' => 'Redis key, cache, queue, and data inspection.',
            'url' => 'https://redisinsight.marketplace-analytics.my.id',
            'icon' => 'pi pi-bolt',
            'risk' => 'high',
        ],
    ],
];
