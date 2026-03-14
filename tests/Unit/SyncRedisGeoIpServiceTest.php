<?php

declare(strict_types=1);

namespace Mpanius\LaravelRedisGeoIp\Tests\Unit;

use Mpanius\LaravelRedisGeoIp\Config\RedisGeoIpConfig;
use Mpanius\LaravelRedisGeoIp\Contracts\CountryDatasetSource;
use Mpanius\LaravelRedisGeoIp\Enums\RedisGeoIpMode;
use Mpanius\LaravelRedisGeoIp\Redis\RedisGeoIpKeys;
use Mpanius\LaravelRedisGeoIp\Redis\RedisGeoIpStore;
use Mpanius\LaravelRedisGeoIp\Services\SyncRedisGeoIpService;
use Mpanius\LaravelRedisGeoIp\Sources\DownloadedSourceFile;
use Mpanius\LaravelRedisGeoIp\Tests\Support\FakeRedisClient;
use PHPUnit\Framework\TestCase;

final class SyncRedisGeoIpServiceTest extends TestCase
{
    public function test_it_syncs_and_marks_dataset_as_fresh(): void
    {
        $client = new FakeRedisClient();
        $store = new RedisGeoIpStore($client, new RedisGeoIpKeys('{geoip}:country'), 2);
        $source = new class implements CountryDatasetSource {
            public function download(?string $url = null): DownloadedSourceFile
            {
                $path = tempnam(sys_get_temp_dir(), 'geoip-sync-');

                file_put_contents(
                    $path,
                    "network,country,country_code,continent_code\n"
                    . "1.2.3.0/24,Australia,AU,OC\n"
                    . "2001:db8::/126,Exampleland,EX,EU\n",
                );

                return new DownloadedSourceFile($path, $url ?? 'https://example.test/geo.csv');
            }
        };

        $service = new SyncRedisGeoIpService(
            store: $store,
            source: $source,
            config: RedisGeoIpConfig::fromArray([
                'mode' => 'dual',
                'redis' => ['prefix' => '{geoip}:country'],
                'sync' => ['refresh_after_hours' => 24, 'keep_versions' => 2, 'batch_size' => 2],
            ]),
        );

        $stats = $service->sync(force: true, modeOverride: RedisGeoIpMode::Dual);

        self::assertNotNull($stats);
        self::assertFalse($service->needsRefresh());
        self::assertSame('LOAD', $client->functionCalls[0]['operation']);
    }
}
