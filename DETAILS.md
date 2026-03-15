# Laravel Redis GeoIP Details

[README.md](./README.md) · [README_RU.md](./README_RU.md) · [DETAILS_RU.md](./DETAILS_RU.md)

## Source Dataset Notes

- IPLocate currently redirects the `.csv` endpoint to a ZIP archive.
- The ZIP archive contains a single CSV file such as `ip-to-country-20260314.csv`.
- The observed CSV header is `network,continent_code,country_code,country_name`.
- The package imports `network` and `country_code` only. `continent_code` and `country_name` are ignored on purpose.

## Compatibility Matrix

This package requires Redis Functions support: `FUNCTION LOAD`, `FCALL_RO`, sorted sets, `UNLINK`, and standard pipelining.

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
| `mpanius/laravel-redis-geoip` | inside Redis Functions | No PHP range lookup, shared dataset for all heads, versioned imports, good fit for high QPS country lookup | Country-code-only, requires Redis Functions and `phpredis` |
| `torann/laravel-geoip` | inside PHP via driver | Mature Laravel package, many drivers, easy drop-in | Lookup still happens in PHP or remote service; not a native Redis offload path |
| `stevebauman/location` | remote HTTP providers | Simple for admin tools or low-volume enrichment | Network latency, provider limits, external dependency, not suitable for hot-path lookup |
| `geoip2/geoip2` or direct MMDB readers | inside PHP / native library binding | Good if you need full MaxMind-style datasets such as city or ASN | Each head needs local database updates; lookup still happens in the app tier |
| nginx / proxy `geoip2` + headers | web edge / proxy | Very fast and fully offloaded from PHP | Tied to proxy config and trusted headers; less portable across environments |
| `geoip_redis` pattern | inside Redis | Same general offload idea, proven precursor | IPv4-focused and not packaged for modern Laravel workflows |

Choose this package when you want country lookup in Laravel but the actual range search must stay out of PHP and out of proxy headers.
