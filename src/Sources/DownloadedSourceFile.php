<?php

namespace Mpanius\LaravelRedisGeoIp\Sources;

final class DownloadedSourceFile
{
    /**
     * @param array<int, string>|null $cleanupPaths
     */
    public function __construct(
        public readonly string $path,
        public readonly string $url,
        private readonly ?array $cleanupPaths = null,
    ) {
    }

    public function cleanup(): void
    {
        $paths = $this->cleanupPaths ?? [$this->path];

        foreach (array_unique($paths) as $path) {
            if (is_string($path) && is_file($path)) {
                unlink($path);
            }
        }
    }
}
