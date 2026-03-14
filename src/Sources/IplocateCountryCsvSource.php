<?php

namespace Mpanius\LaravelRedisGeoIp\Sources;

use InvalidArgumentException;
use RuntimeException;
use Mpanius\LaravelRedisGeoIp\Config\RedisGeoIpConfig;
use Mpanius\LaravelRedisGeoIp\Contracts\CountryDatasetSource;

final class IplocateCountryCsvSource implements CountryDatasetSource
{
    public function __construct(
        private readonly RedisGeoIpConfig $config,
    ) {
    }

    public function download(?string $url = null): DownloadedSourceFile
    {
        $resolvedUrl = $this->config->resolvedSourceUrl($url);
        $target = tempnam(sys_get_temp_dir(), 'redis-geoip-');

        if ($target === false) {
            throw new RuntimeException('Unable to allocate a temporary file for GeoIP sync.');
        }

        $readHandle = fopen($resolvedUrl, 'rb', false, $this->streamContext());
        if ($readHandle === false) {
            unlink($target);
            throw new RuntimeException("Unable to download GeoIP CSV from [{$resolvedUrl}].");
        }

        $writeHandle = fopen($target, 'wb');
        if ($writeHandle === false) {
            fclose($readHandle);
            unlink($target);
            throw new RuntimeException('Unable to open a temporary GeoIP CSV file for writing.');
        }

        stream_copy_to_stream($readHandle, $writeHandle);
        fclose($readHandle);
        fclose($writeHandle);

        return new DownloadedSourceFile(
            path: $target,
            url: $resolvedUrl,
        );
    }

    private function streamContext()
    {
        $headers = ['User-Agent: ' . $this->config->sourceUserAgent()];

        return stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => $this->config->sourceTimeout(),
                'ignore_errors' => false,
                'header' => implode("\r\n", $headers),
            ],
        ]);
    }
}
