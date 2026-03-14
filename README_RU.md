# Laravel Redis GeoIP

Пакет для нативного country lookup по IP через Redis в Laravel.

Идея простая: PHP не ищет диапазоны IP сам и не читает `mmdb` в рантайме.
Laravel только нормализует IP и вызывает `FCALL_RO`, а lookup полностью выполняется внутри Redis.

## Что умеет

- режим `ipv4-only`
- режим `dual` для `IPv4 + IPv6`
- работа через `phpredis::fcall_ro()` и `phpredis::function('LOAD', ...)`
- Redis Functions с `flags={'no-writes'}`
- загрузка country dataset из IPLocate CSV
- versioned datasets и переключение `active_version`
- без lookup-логики диапазонов внутри PHP

## Требования

- PHP 8.3+
- Laravel 11 или 12
- `ext-redis`
- Redis 7+
- клиент `phpredis`

## Установка

```bash
composer require mpanius/laravel-redis-geoip
php artisan vendor:publish --provider="Mpanius\LaravelRedisGeoIp\LaravelRedisGeoIpServiceProvider" --tag="redis-geoip-config"
```

Желательно использовать отдельное Redis connection с отключёнными serializer/compression:

```php
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

## Конфиг

Основной конфиг лежит в `config/redis-geoip.php`.

Ключевые параметры:

- `mode`: `ipv4` или `dual`
- `redis.connection`: Redis connection для lookup dataset
- `redis.prefix`: базовый prefix для ключей
- `source.url`: URL IPLocate CSV
- `source.api_key`: API key
- `sync.refresh_after_hours`: через сколько часов dataset считается устаревшим
- `sync.keep_versions`: сколько версий хранить
- `sync.batch_size`: размер батча при загрузке

## Режимы работы

### `ipv4`

Для IPv4 пакет хранит диапазоны в numeric `zset`:

- `score`: верхняя граница диапазона как unsigned integer
- `member`: `min\tcountry_code\tcontinent_code\tcountry`

Lookup:

1. IP нормализуется в `uint32`
2. вызывается Redis Function `geoip_country_lookup_v4`
3. Redis делает `ZRANGE ... BYSCORE LIMIT 0 1`
4. Redis проверяет нижнюю границу и возвращает страну

### `dual`

В dual mode пакет хранит:

- IPv4 в numeric `zset`
- IPv6 в lexicographic `zset`

Для IPv6 member выглядит так:

```text
max_hex_32\tmin_hex_32\tcountry_code\tcontinent_code\tcountry
```

Это важно, потому что Redis score хранится как `double`, а он не подходит для точного хранения 128-bit IPv6.

## Команда синхронизации

```bash
php artisan redis-geoip:sync
php artisan redis-geoip:sync --force
php artisan redis-geoip:sync --mode=dual
```

Что делает команда:

1. скачивает CSV
2. загружает или обновляет Redis Function library
3. импортирует диапазоны в versioned keys
4. сохраняет metadata
5. переключает `active_version`
6. удаляет старые версии

Команду удобно запускать часто, например каждый час. Если свежий dataset уже есть, она ничего не делает.

Пример scheduler:

```php
use Illuminate\Support\Facades\Schedule;
use Mpanius\LaravelRedisGeoIp\Commands\SyncRedisGeoIpCommand;

Schedule::command(SyncRedisGeoIpCommand::class, ['--isolated' => true])
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();
```

## Runtime usage

```php
use Mpanius\LaravelRedisGeoIp\Contracts\CountryResolver;

$lookup = app(CountryResolver::class)->resolve($request->ip());
```

Если lookup найден:

- `$lookup->countryCode`
- `$lookup->continentCode`
- `$lookup->country`
- `$lookup->version`
- `$lookup->family`

## Какие ключи создаются в Redis

- `{geoip}:country:active_version`
- `{geoip}:country:versions`
- `{geoip}:country:meta:{version}`
- `{geoip}:country:v:{version}:v4`
- `{geoip}:country:v:{version}:v6`

## Ограничения

- пакет сейчас решает только country lookup
- request path не умеет читать `mmdb` и специально этого не делает
- поддерживается только `phpredis`
- для production лучше использовать отдельный Redis instance или как минимум отдельный Redis DB

## Тесты

```bash
composer install
composer test
```

## Лицензия

MIT

