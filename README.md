# Laravel Redis GeoIP

[README_RU.md](./README_RU.md) · [DETAILS.md](./DETAILS.md) · [DETAILS_RU.md](./DETAILS_RU.md)

Redis-backed IPv4 country lookup for Laravel using `phpredis`, Redis 7+ Functions, and IPLocate CSV imports.

This package is optimized for a narrow hot-path use case:

- country lookup only
- IPv4 only in the current stable package surface
- lookup runs inside Redis, not in PHP
- one shared dataset for all Laravel heads

Laravel only normalizes the incoming IP and calls `FCALL_RO`; the range search happens inside Redis. The imported runtime dataset stores `country_code` only. `continent_code` and country names from the source CSV are ignored on purpose.

## What You Get

- native `phpredis` integration via `fcall_ro()` and `function('LOAD', ...)`
- Redis Functions registered with `flags={'no-writes'}`
- versioned imports with atomic `active_version` switch
- ZIP-aware IPLocate download handling
- soft runtime behavior: invalid IP input returns `null`
- tolerant sync behavior: malformed CSV rows are skipped and counted

## Requirements

- PHP 8.3+
- Laravel 11 or 12
- `ext-redis` with `function()` and `fcall_ro()` support
- `ext-zip`
- Redis 7.0+
- `phpredis` client

## Installation

```bash
composer require mpanius/laravel-redis-geoip
php artisan vendor:publish --provider="Mpanius\LaravelRedisGeoIp\LaravelRedisGeoIpServiceProvider" --tag="redis-geoip-config"
```

Configure a dedicated Redis connection with serializer and compression disabled.

```php
// config/database.php
'redis' => [
    'geoip' => [
        'host' => env('REDIS_GEOIP_HOST', env('REDIS_HOST')),
        'port' => env('REDIS_GEOIP_PORT', env('REDIS_PORT', 6379)),
        'password' => env('REDIS_GEOIP_PASSWORD', env('REDIS_PASSWORD')),
        'database' => env('REDIS_GEOIP_DB', 12),
        'prefix' => '',
        'options' => [
            'serializer' => 0,
            'compression' => 0,
        ],
    ],
],
```

Keep the hash tag in the prefix when you customize it. The default `{geoip}:country` keeps control keys and dataset keys in one hash slot for Redis Cluster and compatible platforms.

## Configuration

```php
return [
    'redis' => [
        'connection' => 'geoip',
        'prefix' => '{geoip}:country',
    ],

    'source' => [
        'url' => 'https://www.iplocate.io/download/ip-to-country.csv?apikey=%apikey%&variant=daily',
        'api_key' => env('REDIS_GEOIP_API_KEY'),
    ],

    'sync' => [
        'refresh_after_hours' => 24,
        'keep_versions' => 2,
        'batch_size' => 1000,
    ],
];
```

Important:

- the current release is IPv4-only
- use a dedicated Redis connection with serializer and compression disabled
- keep the `{geoip}` hash tag in the prefix if you change it

## How Lookup Works

The package stores IPv4 ranges in a numeric sorted set:

- `score`: max IPv4 as unsigned integer
- `member`: `min\tcountry_code`

Runtime flow:

1. normalize IPv4 to `uint32`
2. call Redis Function `geoip_country_lookup_v4`
3. Redis performs `ZRANGE ... BYSCORE LIMIT 0 1`
4. Redis validates the lower bound and returns `country_code`

## Sync Command

```bash
php artisan redis-geoip:sync
php artisan redis-geoip:sync --force
php artisan redis-geoip:sync --url="https://example.test/custom.csv"
```

What it does:

1. downloads the IPLocate CSV file
2. loads or replaces the Redis Function library
3. imports the CSV into versioned Redis keys
4. writes metadata
5. switches `active_version`
6. prunes old versions

Malformed CSV rows, non-IPv4 networks, and rows with empty country codes are skipped and counted instead of aborting the whole import.

Recommended scheduler:

```php
use Illuminate\Support\Facades\Schedule;
use Mpanius\LaravelRedisGeoIp\Commands\SyncRedisGeoIpCommand;

Schedule::command(SyncRedisGeoIpCommand::class, ['--isolated' => true])
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();
```

The command itself skips work if the dataset is newer than `refresh_after_hours`.

## Runtime Usage

```php
use Mpanius\LaravelRedisGeoIp\Contracts\CountryResolver;

$countryCode = app(CountryResolver::class)->resolve($request->ip());

if ($countryCode !== null) {
    // e.g. "DE"
}
```

Invalid or unsupported IP input returns `null`.

## Redis Keys

The package keeps a small control plane and versioned datasets:

- `{geoip}:country:active_version`
- `{geoip}:country:versions`
- `{geoip}:country:meta:{version}`
- `{geoip}:country:v:{version}:v4`

## Further Reading

Compatibility, multi-head deployment, source dataset notes, and comparison with alternatives are in [DETAILS.md](./DETAILS.md).

## Notes

- The request path does not parse MMDB files.
- The package currently targets country-level lookups only.
- The current stable package surface is IPv4-only.
- `phpredis` is required; Predis is intentionally not supported.
- A dedicated Redis instance or at least a dedicated Redis DB is strongly recommended.

## Testing

```bash
composer install
composer test
```

## License

MIT
