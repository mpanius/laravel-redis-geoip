<?php

namespace Mpanius\LaravelRedisGeoIp\Data;

use Mpanius\LaravelRedisGeoIp\Enums\RedisGeoIpMode;

final class ImportStats
{
    public function __construct(
        public readonly string $version,
        public readonly RedisGeoIpMode $mode,
        public readonly int $importedIpv4,
        public readonly int $importedIpv6,
        public readonly int $skippedIpv6,
        public readonly string $sourceUrl,
        public readonly string $syncedAt,
    ) {
    }

    /**
     * @return array<string, int|string>
     */
    public function toMetadataArray(): array
    {
        return [
            'version' => $this->version,
            'mode' => $this->mode->value,
            'imported_ipv4' => $this->importedIpv4,
            'imported_ipv6' => $this->importedIpv6,
            'skipped_ipv6' => $this->skippedIpv6,
            'source_url' => $this->sourceUrl,
            'synced_at' => $this->syncedAt,
        ];
    }
}
