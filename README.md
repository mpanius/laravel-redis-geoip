# Laravel Redis GeoIP

Native Redis-backed country lookup for Laravel with `phpredis`, Redis 7+ Functions, and IPLocate CSV imports.

`laravel-redis-geoip` is designed for high-throughput country resolution where PHP should not perform IP range lookups itself.
Laravel only normalizes the incoming IP and calls `FCALL_RO`; the actual lookup runs inside Redis.

## Features

- `ipv4-only` mode with numeric `zset` lookups
- `dual` mode for `IPv4 + IPv6`
- native `phpredis` integration via `fcall_ro()` and `function('LOAD', ...)`
- Redis Functions registered with `flags={'no-writes'}` for read-only execution
- daily IPLocate CSV sync with versioned datasets
- no MMDB parsing in the request path
- Laravel package built with `spatie/laravel-package-tools`

## Requirements

- PHP 8.3+
- Laravel 11 or 12
- `ext-redis`
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

## Configuration

```php
return [
    'mode' => 'ipv4', // or 'dual'

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

## Import Modes

### `ipv4`

Stores IPv4 ranges in a numeric sorted set:

- `score`: max IPv4 as unsigned integer
- `member`: `min\tcountry_code\tcontinent_code\tcountry`

Lookup flow:

1. normalize IPv4 to `uint32`
2. call Redis Function `geoip_country_lookup_v4`
3. Redis performs `ZRANGE ... BYSCORE LIMIT 0 1`
4. Redis validates the lower bound and returns country data

### `dual`

Uses two datasets:

- IPv4 dataset as described above
- IPv6 dataset in a lexicographic sorted set

IPv6 member format:

```text
max_hex_32\tmin_hex_32\tcountry_code\tcontinent_code\tcountry
```

This avoids the precision limit of Redis `double` scores for 128-bit IPv6 addresses.

## Sync Command

```bash
php artisan redis-geoip:sync
php artisan redis-geoip:sync --force
php artisan redis-geoip:sync --mode=dual
php artisan redis-geoip:sync --url="https://example.test/custom.csv"
```

What it does:

1. downloads the IPLocate CSV file
2. loads or replaces the Redis Function library
3. imports the CSV into versioned Redis keys
4. writes metadata
5. switches `active_version`
6. prunes old versions

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

$lookup = app(CountryResolver::class)->resolve($request->ip());

if ($lookup !== null) {
    $lookup->countryCode;   // e.g. "DE"
    $lookup->continentCode; // e.g. "EU"
    $lookup->country;       // e.g. "Germany"
    $lookup->version;       // imported dataset version
    $lookup->family;        // ipv4 or ipv6
}
```

## Redis Keys

The package keeps a small control plane and versioned datasets:

- `{geoip}:country:active_version`
- `{geoip}:country:versions`
- `{geoip}:country:meta:{version}`
- `{geoip}:country:v:{version}:v4`
- `{geoip}:country:v:{version}:v6`

## Notes

- The request path does not parse MMDB files.
- The package currently targets country-level lookups only.
- `phpredis` is required; Predis is intentionally not supported.
- A dedicated Redis instance or at least a dedicated Redis DB is strongly recommended.

## Testing

```bash
composer install
composer test
```

## License

MIT

