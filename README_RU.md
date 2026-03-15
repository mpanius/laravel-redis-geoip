# Laravel Redis GeoIP

Пакет для нативного country lookup по IP через Redis в Laravel.

Идея простая: PHP не ищет диапазоны IP сам и не читает `mmdb` в рантайме.
Laravel только нормализует IP и вызывает `FCALL_RO`, а lookup полностью выполняется внутри Redis.
Сейчас IPLocate отдаёт CSV dataset как ZIP-архив, и пакет во время sync прозрачно извлекает из него внутренний CSV.

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
- `ext-redis` с поддержкой `function()` и `fcall_ro()`
- `ext-zip`
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

Если меняете prefix, сохраняйте hash tag. Значение по умолчанию `{geoip}:country` удерживает служебные ключи и dataset keys в одном hash slot для Redis Cluster и совместимых систем.

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

## Совместимость

Пакет требует Redis Functions: `FUNCTION LOAD`, `FCALL_RO`, sorted sets, `UNLINK` и обычный pipeline. Ниже матрица поддержки именно для этого пакета.

| Backend | Статус | Комментарий |
| --- | --- | --- |
| Redis OSS 7.0+ / Redis CE 7.0+ / Redis Stack 7.0+ | поддерживается | Базовый target. |
| Redis Enterprise / Redis Cloud | поддерживается | В официальной документации Redis `FUNCTION LOAD` и `FCALL_RO` помечены как совместимые. |
| Valkey 7.0+ | поддерживается | Valkey документирует `FUNCTION LOAD`, `FCALL` и `FCALL_RO`. |
| AWS ElastiCache provisioned Redis OSS / Valkey | поддерживается | Для node-based ElastiCache команды Redis Functions доступны. |
| Upstash Redis | поддерживается с оговорками | Upstash документирует Redis Functions и заявляет совместимость протокола до Redis 8.2. Использовать нужно native Redis protocol endpoint, а не REST SDK. |
| AWS ElastiCache Serverless | не поддерживается | AWS явно запрещает `FUNCTION`, `FUNCTION LOAD`, `FCALL` и `FCALL_RO`. |
| AWS MemoryDB | не поддерживается | AWS явно относит `FUNCTION`, `FCALL` и `FCALL_RO` к unsupported commands. |
| Dragonfly | не поддерживается | Dragonfly публично описывает совместимость примерно на уровне Redis 5 API; Redis 7 Functions в этот объём не входят. |
| KeyDB | не поддерживается политикой пакета | В текущей документации KeyDB описаны `SCRIPT` commands и общая Redis parity, но нет документации по Redis Functions / `FCALL_RO`. До явного подтверждения пакет считает такую среду неподдерживаемой. |

## Несколько голов приложения

`laravel-redis-geoip` рассчитан на несколько Laravel-серверов с общим Redis-хранилищем.

1. Все application heads подключаются к одному Redis instance, cluster или managed endpoint.
2. У всех должны совпадать `redis.connection` и `redis.prefix`.
3. Команду `redis-geoip:sync` лучше запускать только на одной scheduler-head.
4. Остальные головы только читают общий dataset через lookup.

Практические детали:

- Локальных MMDB-файлов и локального PHP cache здесь нет; все головы читают один и тот же `active_version`.
- Импорт versioned: сначала создаются новые dataset keys, потом переключается `active_version`, поэтому cutover атомарен с точки зрения приложения.
- Prefix по умолчанию использует hash tag `{geoip}`, чтобы в Redis Cluster служебные ключи и dataset keys попадали в один slot.
- Если несколько голов всё же одновременно запустят sync, isolation у Laravel-команды и scheduler locks снижают риск дублей, но одна выделенная sync-head всё равно лучше.
- Если несколько Laravel-приложений должны разделять один dataset, используйте одинаковый prefix. Если нужна изоляция, задайте разные prefix.

## Сравнение с альтернативами

| Решение | Где выполняется lookup | Сильные стороны | Ограничения |
| --- | --- | --- | --- |
| `mpanius/laravel-redis-geoip` | внутри Redis Functions | Нет range-lookup в PHP, общий dataset для всех голов, versioned imports, хорошо подходит для high-QPS country lookup | Только country lookup, требует Redis Functions и `phpredis` |
| `torann/laravel-geoip` | в PHP через driver | Зрелый Laravel package, много драйверов, простой drop-in | Lookup всё равно выполняется в PHP или через внешний сервис; это не native Redis offload |
| `stevebauman/location` | через внешние HTTP providers | Удобно для backoffice и low-volume enrichment | Сетевой hop, лимиты провайдера, внешняя зависимость, не подходит для горячего request path |
| `geoip2/geoip2` или прямое чтение MMDB | в PHP / через native binding | Подходит, если нужен MaxMind-стиль dataset уровня city/ASN | Каждую голову надо отдельно обновлять; lookup остаётся в app tier |
| nginx / proxy `geoip2` + headers | на уровне edge / proxy | Очень быстро и полностью вынесено из PHP | Жёсткая привязка к proxy config и trusted headers; менее переносимо |
| `geoip_redis` pattern | внутри Redis | Та же общая идея offload в Redis, полезный предшественник | В первую очередь IPv4 и не оформлено как современный Laravel package |

Этот пакет стоит выбирать, когда вам нужен country lookup в Laravel, но сам поиск диапазона должен жить не в PHP и не в proxy headers.

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
