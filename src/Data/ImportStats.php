<?php

namespace Mpanius\LaravelRedisGeoIp\Data;

final class ImportStats
{
    public function __construct(
        public readonly string $version,
        public readonly int $importedIpv4,
        public readonly int $skippedEmpty,
        public readonly int $skippedInvalid,
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
            'imported_ipv4' => $this->importedIpv4,
            'skipped_empty' => $this->skippedEmpty,
            'skipped_invalid' => $this->skippedInvalid,
            'source_url' => $this->sourceUrl,
            'synced_at' => $this->syncedAt,
        ];
    }
}
