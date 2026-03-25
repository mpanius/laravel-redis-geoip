<?php

return [
    'redis' => [
        'connection' => env('REDIS_GEOIP_CONNECTION', 'default'),
        'prefix' => env('REDIS_GEOIP_PREFIX', '{geoip}:country'),
    ],

    'source' => [
        'driver' => 'iplocate_csv',
        'url' => env(
            'REDIS_GEOIP_SOURCE_URL',
            'https://www.iplocate.io/download/ip-to-country.csv?apikey=%apikey%&variant=daily'
        ),
        'api_key' => env('REDIS_GEOIP_API_KEY'),
        'timeout' => (int) env('REDIS_GEOIP_SOURCE_TIMEOUT', 120),
        'user_agent' => env('REDIS_GEOIP_USER_AGENT', 'mpanius/laravel-redis-geoip'),
    ],

    'sync' => [
        'refresh_after_hours' => (int) env('REDIS_GEOIP_REFRESH_AFTER_HOURS', 24),
        'keep_versions' => (int) env('REDIS_GEOIP_KEEP_VERSIONS', 2),
        'batch_size' => (int) env('REDIS_GEOIP_BATCH_SIZE', 1000),
    ],
];
