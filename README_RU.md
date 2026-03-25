# Laravel Redis GeoIP

[README.md](./README.md) · [DETAILS_RU.md](./DETAILS_RU.md) · [DETAILS.md](./DETAILS.md)

Пакет для Redis-backed lookup страны по IPv4 в Laravel.

Текущий stable scope у пакета узкий и намеренно прагматичный:

- только `country_code`
- только IPv4
- lookup выполняется внутри Redis, а не в PHP
- один общий dataset для всех Laravel-heads

Laravel только нормализует входящий IP и вызывает `FCALL_RO`; поиск диапазона целиком живёт внутри Redis. IPLocate сейчас отдаёт CSV как ZIP-архив, и пакет во время sync автоматически извлекает внутренний CSV. Runtime dataset хранит только `country_code`; `continent_code` и полные названия стран из исходного файла намеренно игнорируются.

## Что даёт пакет

- работа через `phpredis::fcall_ro()` и `phpredis::function('LOAD', ...)`
- Redis Functions с `flags={'no-writes'}`
- versioned imports и атомарное переключение `active_version`
- корректная обработка ZIP-ответа от IPLocate
- мягкое runtime-поведение: invalid IP возвращает `null`
- живучий sync: битые CSV-строки пропускаются и считаются

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

- `redis.connection`: Redis connection для lookup dataset
- `redis.prefix`: базовый prefix для ключей
- `source.url`: URL IPLocate CSV
- `source.api_key`: API key
- `sync.refresh_after_hours`: через сколько часов dataset считается устаревшим
- `sync.keep_versions`: сколько версий хранить
- `sync.batch_size`: размер батча при загрузке

Важно:

- текущий релиз пакета работает только с IPv4
- Redis connection лучше выделить отдельно и отключить serializer/compression
- если меняете prefix, сохраняйте hash tag `{geoip}`

## Как Работает Lookup

Для IPv4 пакет хранит диапазоны в numeric `zset`:

- `score`: верхняя граница диапазона как unsigned integer
- `member`: `min\tcountry_code`

Lookup:

1. IP нормализуется в `uint32`
2. вызывается Redis Function `geoip_country_lookup_v4`
3. Redis делает `ZRANGE ... BYSCORE LIMIT 0 1`
4. Redis проверяет нижнюю границу и возвращает `country_code`

## Команда синхронизации

```bash
php artisan redis-geoip:sync
php artisan redis-geoip:sync --force
```

Что делает команда:

1. скачивает CSV
2. загружает или обновляет Redis Function library
3. импортирует диапазоны в versioned keys
4. сохраняет metadata
5. переключает `active_version`
6. удаляет старые версии

Битые CSV-строки, не-IPv4 сети и строки с пустым `country_code` не валят импорт целиком: они пропускаются и попадают в skip-статистику.

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

$countryCode = app(CountryResolver::class)->resolve($request->ip());
```

Если lookup найден, вы получите строку вроде `DE`.
Если IP невалидный или неподдерживаемый, вернётся `null`.

## Какие ключи создаются в Redis

- `{geoip}:country:active_version`
- `{geoip}:country:versions`
- `{geoip}:country:meta:{version}`
- `{geoip}:country:v:{version}:v4`

## Дополнительно

Совместимость, multi-head deployment, примечания по исходному dataset и сравнение с альтернативами вынесены в [DETAILS_RU.md](./DETAILS_RU.md).

## Ограничения

- пакет сейчас решает только country lookup
- текущий stable package surface ограничен IPv4
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
