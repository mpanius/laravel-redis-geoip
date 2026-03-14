<?php

namespace Mpanius\LaravelRedisGeoIp\Services;

use DateTimeImmutable;
use Mpanius\LaravelRedisGeoIp\Config\RedisGeoIpConfig;
use Mpanius\LaravelRedisGeoIp\Contracts\CountryDatasetSource;
use Mpanius\LaravelRedisGeoIp\Data\ImportStats;
use Mpanius\LaravelRedisGeoIp\Enums\RedisGeoIpMode;
use Mpanius\LaravelRedisGeoIp\Redis\RedisGeoIpStore;

final class SyncRedisGeoIpService
{
    public function __construct(
        private readonly RedisGeoIpStore $store,
        private readonly CountryDatasetSource $source,
        private readonly RedisGeoIpConfig $config,
    ) {
    }

    public function needsRefresh(bool $force = false, ?int $refreshAfterHoursOverride = null): bool
    {
        if ($force) {
            return true;
        }

        $metadata = $this->store->currentMetadata();
        if ($metadata === null || !isset($metadata['synced_at'])) {
            return true;
        }

        $syncedAt = new DateTimeImmutable((string) $metadata['synced_at']);
        $refreshAfterHours = max(1, $refreshAfterHoursOverride ?? $this->config->refreshAfterHours());
        $threshold = (new DateTimeImmutable('now'))->modify("-{$refreshAfterHours} hours");

        return $syncedAt <= $threshold;
    }

    public function sync(
        bool $force = false,
        ?RedisGeoIpMode $modeOverride = null,
        ?string $sourceUrlOverride = null,
        ?int $keepVersionsOverride = null,
        ?int $refreshAfterHoursOverride = null,
    ): ?ImportStats {
        if (!$this->needsRefresh($force, $refreshAfterHoursOverride)) {
            return null;
        }

        $download = $this->source->download($sourceUrlOverride);

        try {
            $version = gmdate('Ymd\THis\Z');
            $mode = $modeOverride ?? $this->config->mode();

            $this->store->loadFunctionLibrary();
            $stats = $this->store->importCsv($download->path, $version, $mode, $download->url);
            $this->store->activateVersion($stats);
            $this->store->pruneVersions($keepVersionsOverride ?? $this->config->keepVersions(), [$version]);

            return $stats;
        } finally {
            $download->cleanup();
        }
    }
}
