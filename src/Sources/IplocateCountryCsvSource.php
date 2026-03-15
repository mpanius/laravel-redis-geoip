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
        $target = tempnam(sys_get_temp_dir(), 'redis-geoip-download-');

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

        if ($this->isZipArchive($target)) {
            return $this->extractCsvFromZipArchive($target, $resolvedUrl);
        }

        return new DownloadedSourceFile(
            path: $target,
            url: $resolvedUrl,
            cleanupPaths: [$target],
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

    private function isZipArchive(string $path): bool
    {
        $signature = file_get_contents($path, false, null, 0, 4);

        return in_array($signature, ["PK\x03\x04", "PK\x05\x06", "PK\x07\x08"], true);
    }

    private function extractCsvFromZipArchive(string $archivePath, string $resolvedUrl): DownloadedSourceFile
    {
        $archive = new \ZipArchive();
        $opened = $archive->open($archivePath);

        if ($opened !== true) {
            unlink($archivePath);

            throw new RuntimeException("Unable to open downloaded GeoIP ZIP archive [{$resolvedUrl}].");
        }

        try {
            $entryName = $this->firstCsvEntryName($archive);

            if ($entryName === null) {
                throw new RuntimeException("GeoIP ZIP archive [{$resolvedUrl}] does not contain a CSV file.");
            }

            $entryStream = $archive->getStream($entryName);
            if ($entryStream === false) {
                throw new RuntimeException("Unable to read CSV entry [{$entryName}] from GeoIP ZIP archive.");
            }

            $csvPath = tempnam(sys_get_temp_dir(), 'redis-geoip-csv-');
            if ($csvPath === false) {
                fclose($entryStream);

                throw new RuntimeException('Unable to allocate a temporary CSV file for GeoIP sync.');
            }

            $writeHandle = fopen($csvPath, 'wb');
            if ($writeHandle === false) {
                fclose($entryStream);
                unlink($csvPath);

                throw new RuntimeException('Unable to open a temporary CSV file extracted from GeoIP ZIP archive.');
            }

            stream_copy_to_stream($entryStream, $writeHandle);
            fclose($entryStream);
            fclose($writeHandle);

            return new DownloadedSourceFile(
                path: $csvPath,
                url: $resolvedUrl,
                cleanupPaths: [$archivePath, $csvPath],
            );
        } finally {
            $archive->close();
        }
    }

    private function firstCsvEntryName(\ZipArchive $archive): ?string
    {
        for ($index = 0; $index < $archive->numFiles; $index++) {
            $entryName = $archive->getNameIndex($index);

            if (!is_string($entryName) || $entryName === '' || str_ends_with($entryName, '/')) {
                continue;
            }

            if (str_ends_with(strtolower($entryName), '.csv')) {
                return $entryName;
            }
        }

        return null;
    }
}
