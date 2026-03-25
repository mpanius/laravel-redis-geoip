<?php

namespace Mpanius\LaravelRedisGeoIp\Redis;

use InvalidArgumentException;
use JsonException;
use Mpanius\LaravelRedisGeoIp\Contracts\RedisClient;
use Mpanius\LaravelRedisGeoIp\Data\CountryRangeRecord;
use Mpanius\LaravelRedisGeoIp\Data\ImportStats;

final class RedisGeoIpStore
{
    public function __construct(
        private readonly RedisClient $client,
        private readonly RedisGeoIpKeys $keys,
        private readonly int $batchSize = 1000,
    ) {
    }

    public function loadFunctionLibrary(): void
    {
        $this->client->redisFunction('LOAD', 'REPLACE', RedisFunctionLibrary::source());
    }

    /**
     * @return array<string, mixed>|null
     */
    public function currentMetadata(): ?array
    {
        $version = $this->client->get($this->keys->activeVersion());

        if (!is_string($version) || $version === '') {
            return null;
        }

        $payload = $this->client->get($this->keys->meta($version));

        if (!is_string($payload) || $payload === '') {
            return null;
        }

        try {
            $decoded = json_decode($payload, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    public function importCsv(
        string $path,
        string $version,
        string $sourceUrl,
    ): ImportStats {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new InvalidArgumentException("Unable to open GeoIP CSV file [{$path}].");
        }

        $v4Key = $this->keys->datasetKey($version, 'v4');
        $metaKey = $this->keys->meta($version);

        $this->client->unlink($v4Key, $metaKey);

        $header = fgetcsv($handle, escape: '\\');
        if (!is_array($header)) {
            fclose($handle);
            throw new InvalidArgumentException('GeoIP CSV header is missing.');
        }

        $columns = $this->mapCsvColumns($header);
        foreach (['network', 'country_code'] as $column) {
            if (!array_key_exists($column, $columns)) {
                fclose($handle);
                throw new InvalidArgumentException("GeoIP CSV column [{$column}] is required.");
            }
        }

        $ipv4 = [];
        $importedIpv4 = 0;
        $skippedEmpty = 0;
        $skippedInvalid = 0;

        while (($row = fgetcsv($handle, escape: '\\')) !== false) {
            if (!is_array($row) || $row === []) {
                continue;
            }

            if (
                !array_key_exists($columns['network'], $row)
                || !array_key_exists($columns['country_code'], $row)
            ) {
                $skippedInvalid++;
                continue;
            }

            $network = trim((string) $row[$columns['network']]);
            $countryCode = trim((string) $row[$columns['country_code']]);

            if ($network === '') {
                $skippedInvalid++;
                continue;
            }

            if ($countryCode === '') {
                $skippedEmpty++;
                continue;
            }

            try {
                $record = CountryRangeRecord::fromCsv(
                    network: $network,
                    countryCode: $countryCode,
                );
            } catch (InvalidArgumentException) {
                $skippedInvalid++;
                continue;
            }

            $ipv4[] = [$record->score(), $record->toRedisMember()];
            $importedIpv4++;

            if (count($ipv4) >= $this->batchSize) {
                $this->flushBatch($v4Key, $ipv4);
            }
        }

        fclose($handle);

        $this->flushBatch($v4Key, $ipv4);

        return new ImportStats(
            version: $version,
            importedIpv4: $importedIpv4,
            skippedEmpty: $skippedEmpty,
            skippedInvalid: $skippedInvalid,
            sourceUrl: $sourceUrl,
            syncedAt: gmdate(DATE_ATOM),
        );
    }

    public function activateVersion(ImportStats $stats): void
    {
        $encoded = json_encode($stats->toMetadataArray(), JSON_THROW_ON_ERROR);

        $this->client->multi(\Redis::PIPELINE);
        $this->client->set($this->keys->meta($stats->version), $encoded);
        $this->client->zAdd($this->keys->versions(), (float) strtotime($stats->syncedAt), $stats->version);
        $this->client->set($this->keys->activeVersion(), $stats->version);
        $this->client->exec();
    }

    /**
     * @param array<int, string> $protectedVersions
     */
    public function pruneVersions(int $keepVersions, array $protectedVersions = []): void
    {
        $versions = $this->client->zRevRange($this->keys->versions(), 0, -1);

        if (!is_array($versions) || $versions === []) {
            return;
        }

        $kept = 0;
        foreach ($versions as $version) {
            $isProtected = in_array($version, $protectedVersions, true);

            if ($isProtected || $kept < $keepVersions) {
                $kept++;
                continue;
            }

            $this->client->unlink(
                $this->keys->datasetKey($version, 'v4'),
                $this->keys->meta($version),
            );
            $this->client->zRem($this->keys->versions(), $version);
        }
    }

    /**
     * @param array<int, array{0: float, 1: string}> $entries
     */
    private function flushBatch(string $key, array &$entries): void
    {
        if ($entries === []) {
            return;
        }

        $this->client->multi(\Redis::PIPELINE);

        foreach ($entries as [$score, $member]) {
            $this->client->zAdd($key, $score, $member);
        }

        $this->client->exec();
        $entries = [];
    }

    /**
     * @param array<int, string|null> $header
     * @return array<string, int>
     */
    private function mapCsvColumns(array $header): array
    {
        $normalized = [];

        foreach ($header as $index => $value) {
            if (!is_string($value)) {
                continue;
            }

            $column = strtolower(trim($value));
            $column = preg_replace('/^\xEF\xBB\xBF/', '', $column) ?? $column;

            if ($column === '') {
                continue;
            }

            $normalized[$column] = $index;
        }

        return $normalized;
    }
}
