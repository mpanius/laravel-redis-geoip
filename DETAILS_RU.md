# Laravel Redis GeoIP Details

[README_RU.md](./README_RU.md) · [README.md](./README.md) · [DETAILS.md](./DETAILS.md)

## Примечания По Dataset

- Текущий релиз пакета — только IPv4.
- Сейчас IPLocate редиректит `.csv` endpoint на ZIP-архив.
- Внутри ZIP обычно лежит один CSV-файл вида `ip-to-country-20260314.csv`.
- Наблюдаемый header: `network,continent_code,country_code,country_name`.
- Пакет намеренно импортирует только `network` и `country_code`. `continent_code` и `country_name` игнорируются.

## Совместимость

Пакет требует Redis Functions: `FUNCTION LOAD`, `FCALL_RO`, sorted sets, `UNLINK` и обычный pipeline.

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

## Несколько Голов Приложения

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

## Сравнение С Альтернативами

| Решение | Где выполняется lookup | Сильные стороны | Ограничения |
| --- | --- | --- | --- |
| `mpanius/laravel-redis-geoip` | внутри Redis Functions | Нет range-lookup в PHP, общий dataset для всех голов, versioned imports, хорошо подходит для high-QPS country lookup | Только `country_code`, требует Redis Functions и `phpredis` |
| `torann/laravel-geoip` | в PHP через driver | Зрелый Laravel package, много драйверов, простой drop-in | Lookup всё равно выполняется в PHP или через внешний сервис; это не native Redis offload |
| `stevebauman/location` | через внешние HTTP providers | Удобно для backoffice и low-volume enrichment | Сетевой hop, лимиты провайдера, внешняя зависимость, не подходит для горячего request path |
| `geoip2/geoip2` или прямое чтение MMDB | в PHP / через native binding | Подходит, если нужен MaxMind-стиль dataset уровня city/ASN | Каждую голову надо отдельно обновлять; lookup остаётся в app tier |
| nginx / proxy `geoip2` + headers | на уровне edge / proxy | Очень быстро и полностью вынесено из PHP | Жёсткая привязка к proxy config и trusted headers; менее переносимо |
| `geoip_redis` pattern | внутри Redis | Та же общая идея offload в Redis, полезный предшественник | В первую очередь IPv4 и не оформлено как современный Laravel package |

Этот пакет стоит выбирать, когда вам нужен country lookup в Laravel, но сам поиск диапазона должен жить не в PHP и не в proxy headers.
