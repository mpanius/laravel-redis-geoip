<?php

namespace Mpanius\LaravelRedisGeoIp\Sources;

final class DownloadedSourceFile
{
    public function __construct(
        public readonly string $path,
        public readonly string $url,
    ) {
    }

    public function cleanup(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }
    }
}
