# Laravel Redis GeoIP

[README_RU.md](./README_RU.md)

Native Redis-backed country lookup for Laravel with `phpredis`, Redis 7+ Functions, and IPLocate CSV imports.

`laravel-redis-geoip` is designed for high-throughput country resolution where PHP should not perform IP range lookups itself.
Laravel only normalizes the incoming IP and calls `FCALL_RO`; the actual lookup runs inside Redis.
IPLocate currently serves the CSV dataset as a ZIP archive, and the package transparently extracts the inner CSV during sync.
The runtime dataset stores country and continent codes only; full country names from the source file are intentionally ignored.

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
- `member`: `min\tcountry_code\tcontinent_code`

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
max_hex_32\tmin_hex_32\tcountry_code\tcontinent_code
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

## Compatibility Matrix

This package requires Redis Functions support: `FUNCTION LOAD`, `FCALL_RO`, sorted sets, `UNLINK`, and standard pipelining. The matrix below is the support policy for this package.

| Backend | Status | Notes |
| --- | --- | --- |
| Redis OSS 7.0+ / Redis CE 7.0+ / Redis Stack 7.0+ | supported | Native target. |
| Redis Enterprise / Redis Cloud | supported | Redis docs list `FUNCTION LOAD` and `FCALL_RO` as compatible. |
| Valkey 7.0+ | supported | Valkey documents `FUNCTION LOAD`, `FCALL`, and `FCALL_RO`. |
| AWS ElastiCache provisioned Redis OSS / Valkey | supported | Node-based ElastiCache supports Redis Functions commands. |
| Upstash Redis | supported with caveats | Upstash documents Redis Functions support and lists compatibility up to Redis 8.2. Use the native Redis protocol endpoint; this package is not built for the REST SDK. |
| AWS ElastiCache Serverless | not supported | AWS explicitly restricts `FUNCTION`, `FUNCTION LOAD`, `FCALL`, and `FCALL_RO`. |
| AWS MemoryDB | not supported | AWS explicitly lists `FUNCTION`, `FCALL`, and `FCALL_RO` as unsupported. |
| Dragonfly | not supported | Dragonfly publicly advertises compatibility with roughly the Redis 5 API surface; Redis 7 Functions are outside that target. |
| KeyDB | not supported by policy | Current KeyDB docs document `SCRIPT` commands and Redis parity in general, but do not document Redis Functions / `FCALL_RO`. This package treats that as unsupported until proven otherwise. |

## Multiple Application Heads

`laravel-redis-geoip` is designed for many Laravel heads sharing one Redis dataset.

1. Point every application server at the same Redis instance, cluster, or managed endpoint.
2. Keep the same `redis.connection` and `redis.prefix` on every head.
3. Run `redis-geoip:sync` on one scheduler head only.
4. Let all other heads do read-only lookups through the shared dataset.

Operational notes:

- The package stores no local MMDB files and no local PHP cache; all heads read the same `active_version` key.
- Imports are versioned. A sync writes new dataset keys first and only then switches `active_version`, so the cutover is atomic from the application point of view.
- The default prefix uses the `{geoip}` hash tag so Redis Cluster routes control keys and dataset keys to one slot.
- If multiple heads accidentally run the sync command, Laravel command isolation and scheduler locking reduce duplicate work, but a single designated sync head is still the cleanest setup.
- If several Laravel applications should share one country dataset, reuse the same prefix. If they must be isolated, use different prefixes.

## Comparison

| Solution | Lookup runs where | Strengths | Tradeoffs |
| --- | --- | --- | --- |
| `mpanius/laravel-redis-geoip` | inside Redis Functions | No PHP range lookup, shared dataset for all heads, versioned imports, good fit for high QPS country lookup | Country-only, requires Redis Functions and `phpredis` |
| `torann/laravel-geoip` | inside PHP via driver | Mature Laravel package, many drivers, easy drop-in | Lookup still happens in PHP or remote service; not a native Redis offload path |
| `stevebauman/location` | remote HTTP providers | Simple for admin tools or low-volume enrichment | Network latency, provider limits, external dependency, not suitable for hot-path lookup |
| `geoip2/geoip2` or direct MMDB readers | inside PHP / native library binding | Good if you need full MaxMind-style datasets such as city or ASN | Each head needs local database updates; lookup still happens in the app tier |
| nginx / proxy `geoip2` + headers | web edge / proxy | Very fast and fully offloaded from PHP | Tied to proxy config and trusted headers; less portable across environments |
| `geoip_redis` pattern | inside Redis | Same general offload idea, proven precursor | IPv4-focused and not packaged for modern Laravel workflows |

Choose this package when you want country lookup in Laravel but the actual range search must stay out of PHP and out of proxy headers.

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
