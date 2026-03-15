<?php

declare(strict_types=1);

namespace Mpanius\LaravelRedisGeoIp\Tests\Unit;

use Mpanius\LaravelRedisGeoIp\Config\RedisGeoIpConfig;
use Mpanius\LaravelRedisGeoIp\Sources\IplocateCountryCsvSource;
use PHPUnit\Framework\TestCase;

final class IplocateCountryCsvSourceTest extends TestCase
{
    public function test_it_extracts_csv_payload_from_zip_downloads(): void
    {
        $archivePath = tempnam(sys_get_temp_dir(), 'geoip-archive-');
        self::assertIsString($archivePath);

        $archive = new \ZipArchive();
        self::assertTrue($archive->open($archivePath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE));
        self::assertTrue($archive->addFromString(
            'ip-to-country.csv',
            "network,continent_code,country_code,country_name\n1.2.3.0/24,OC,AU,Australia\n",
        ));
        $archive->close();

        $source = new IplocateCountryCsvSource(RedisGeoIpConfig::fromArray([
            'source' => [
                'url' => 'https://example.test/geo.csv',
            ],
        ]));

        $download = $source->download('file://' . $archivePath);

        try {
            self::assertFileExists($download->path);

            $contents = file_get_contents($download->path);

            self::assertIsString($contents);
            self::assertStringContainsString('country_name', $contents);
            self::assertStringContainsString('Australia', $contents);
        } finally {
            $download->cleanup();

            if (is_file($archivePath)) {
                unlink($archivePath);
            }
        }
    }
}
