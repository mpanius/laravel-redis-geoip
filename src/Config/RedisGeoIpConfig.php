<?php

namespace Mpanius\LaravelRedisGeoIp\Config;

use InvalidArgumentException;
use Mpanius\LaravelRedisGeoIp\Enums\RedisGeoIpMode;

final class RedisGeoIpConfig
{
    public function __construct(
        private readonly RedisGeoIpMode $mode,
        private readonly string $redisConnection,
        private readonly string $prefix,
        private readonly string $sourceUrl,
        private readonly ?string $sourceApiKey,
        private readonly int $sourceTimeout,
        private readonly string $sourceUserAgent,
        private readonly int $refreshAfterHours,
        private readonly int $keepVersions,
        private readonly int $batchSize,
    ) {
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        $redis = $config['redis'] ?? [];
        $source = $config['source'] ?? [];
        $sync = $config['sync'] ?? [];

        return new self(
            mode: RedisGeoIpMode::from((string) ($config['mode'] ?? RedisGeoIpMode::Ipv4->value)),
            redisConnection: (string) ($redis['connection'] ?? 'default'),
            prefix: rtrim((string) ($redis['prefix'] ?? '{geoip}:country'), ':'),
            sourceUrl: (string) ($source['url'] ?? ''),
            sourceApiKey: array_key_exists('api_key', $source) && $source['api_key'] !== null
                ? (string) $source['api_key']
                : null,
            sourceTimeout: max(1, (int) ($source['timeout'] ?? 120)),
            sourceUserAgent: (string) ($source['user_agent'] ?? 'mpanius/laravel-redis-geoip'),
            refreshAfterHours: max(1, (int) ($sync['refresh_after_hours'] ?? 24)),
            keepVersions: max(1, (int) ($sync['keep_versions'] ?? 2)),
            batchSize: max(1, (int) ($sync['batch_size'] ?? 1000)),
        );
    }

    public function mode(): RedisGeoIpMode
    {
        return $this->mode;
    }

    public function redisConnection(): string
    {
        return $this->redisConnection;
    }

    public function prefix(): string
    {
        return $this->prefix;
    }

    public function sourceTimeout(): int
    {
        return $this->sourceTimeout;
    }

    public function sourceUserAgent(): string
    {
        return $this->sourceUserAgent;
    }

    public function refreshAfterHours(): int
    {
        return $this->refreshAfterHours;
    }

    public function keepVersions(): int
    {
        return $this->keepVersions;
    }

    public function batchSize(): int
    {
        return $this->batchSize;
    }

    public function resolvedSourceUrl(?string $override = null): string
    {
        $url = $override ?? $this->sourceUrl;

        if (str_contains($url, '%apikey%')) {
            if ($this->sourceApiKey === null || $this->sourceApiKey === '') {
                throw new InvalidArgumentException('REDIS_GEOIP_API_KEY is required for the configured source URL.');
            }

            $url = str_replace('%apikey%', rawurlencode($this->sourceApiKey), $url);
        }

        return $url;
    }
}
