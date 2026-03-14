<?php

namespace Mpanius\LaravelRedisGeoIp\Redis;

final class RedisGeoIpKeys
{
    public function __construct(
        private readonly string $prefix,
    ) {
    }

    public function rootPrefix(): string
    {
        return $this->prefix;
    }

    public function activeVersion(): string
    {
        return "{$this->prefix}:active_version";
    }

    public function versions(): string
    {
        return "{$this->prefix}:versions";
    }

    public function meta(string $version): string
    {
        return "{$this->prefix}:meta:{$version}";
    }

    public function datasetKey(string $version, string $suffix): string
    {
        return "{$this->prefix}:v:{$version}:{$suffix}";
    }
}
