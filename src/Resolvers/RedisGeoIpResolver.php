<?php

namespace Mpanius\LaravelRedisGeoIp\Resolvers;

use InvalidArgumentException;
use Mpanius\LaravelRedisGeoIp\Contracts\CountryResolver;
use Mpanius\LaravelRedisGeoIp\Contracts\RedisClient;
use Mpanius\LaravelRedisGeoIp\Redis\RedisFunctionLibrary;
use Mpanius\LaravelRedisGeoIp\Redis\RedisGeoIpKeys;
use Mpanius\LaravelRedisGeoIp\Support\IpAddressNormalizer;

final class RedisGeoIpResolver implements CountryResolver
{
    public function __construct(
        private readonly RedisClient $client,
        private readonly RedisGeoIpKeys $keys,
    ) {
    }

    public function resolve(string $ip): ?string
    {
        $version = $this->client->get($this->keys->activeVersion());
        if (!is_string($version) || $version === '') {
            return null;
        }

        try {
            $payload = $this->client->fcallRo(
                RedisFunctionLibrary::LOOKUP_V4,
                [$this->keys->datasetKey($version, 'v4')],
                [IpAddressNormalizer::toUnsignedIntString($ip)],
            );

            return is_string($payload) && $payload !== '' ? $payload : null;
        } catch (InvalidArgumentException) {
            return null;
        }
    }
}
