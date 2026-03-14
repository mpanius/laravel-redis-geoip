<?php

declare(strict_types=1);

namespace Mpanius\LaravelRedisGeoIp\Tests\Unit;

use Mpanius\LaravelRedisGeoIp\Enums\RedisGeoIpMode;
use Mpanius\LaravelRedisGeoIp\Redis\RedisGeoIpKeys;
use Mpanius\LaravelRedisGeoIp\Redis\RedisGeoIpStore;
use Mpanius\LaravelRedisGeoIp\Tests\Support\FakeRedisClient;
use PHPUnit\Framework\TestCase;

final class RedisGeoIpStoreTest extends TestCase
{
    public function test_it_imports_ipv4_only_mode_without_ipv6_ranges(): void
    {
        $client = new FakeRedisClient();
        $keys = new RedisGeoIpKeys('{geoip}:country');
        $store = new RedisGeoIpStore($client, $keys, 2);
        $path = $this->createCsv();

        try {
            $stats = $store->importCsv($path, 'v1', RedisGeoIpMode::Ipv4, 'https://example.test/geo.csv');
        } finally {
            unlink($path);
        }

        self::assertSame(1, $stats->importedIpv4);
        self::assertSame(0, $stats->importedIpv6);
        self::assertSame(1, $stats->skippedIpv6);
        self::assertCount(1, $client->zsets[$keys->datasetKey('v1', 'v4')]);
        self::assertArrayNotHasKey($keys->datasetKey('v1', 'v6'), $client->zsets);
    }

    public function test_it_activates_and_prunes_versions(): void
    {
        $client = new FakeRedisClient();
        $keys = new RedisGeoIpKeys('{geoip}:country');
        $store = new RedisGeoIpStore($client, $keys, 2);
        $path = $this->createCsv();

        try {
            $statsV1 = $store->importCsv($path, 'v1', RedisGeoIpMode::Dual, 'https://example.test/geo.csv');
            $statsV2 = $store->importCsv($path, 'v2', RedisGeoIpMode::Dual, 'https://example.test/geo.csv');
            $statsV3 = $store->importCsv($path, 'v3', RedisGeoIpMode::Dual, 'https://example.test/geo.csv');
        } finally {
            unlink($path);
        }

        $store->activateVersion($statsV1);
        $store->activateVersion($statsV2);
        $store->activateVersion($statsV3);
        $store->pruneVersions(2, ['v3']);

        $metadata = $store->currentMetadata();

        self::assertSame('v3', $metadata['version']);
        self::assertCount(2, $client->zsets[$keys->versions()]);
        self::assertArrayNotHasKey($keys->datasetKey('v1', 'v4'), $client->zsets);
        self::assertArrayHasKey($keys->datasetKey('v3', 'v6'), $client->zsets);
    }

    private function createCsv(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'geoip-test-');

        file_put_contents(
            $path,
            "network,country,country_code,continent_code\n"
            . "1.2.3.0/24,Australia,AU,OC\n"
            . "2001:db8::/126,Exampleland,EX,EU\n",
        );

        return $path;
    }
}
