<?php

namespace Mpanius\LaravelRedisGeoIp\Resolvers;

use Mpanius\LaravelRedisGeoIp\Config\RedisGeoIpConfig;
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
        private readonly RedisGeoIpConfig $config,
    ) {
    }

    public function resolve(string $ip): ?string
    {
        $version = $this->client->get($this->keys->activeVersion());
        if (!is_string($version) || $version === '') {
            return null;
        }

        $family = IpAddressNormalizer::detectFamily($ip);

        if ($family === 'ipv4') {
            $payload = $this->client->fcallRo(
                RedisFunctionLibrary::LOOKUP_V4,
                [$this->keys->datasetKey($version, 'v4')],
                [IpAddressNormalizer::toUnsignedIntString($ip)],
            );

            return is_string($payload) && $payload !== '' ? $payload : null;
        }

        if (!$this->config->mode()->supportsIpv6()) {
            return null;
        }

        $payload = $this->client->fcallRo(
            RedisFunctionLibrary::LOOKUP_V6,
            [$this->keys->datasetKey($version, 'v6')],
            [IpAddressNormalizer::toFixedHexString($ip)],
        );

        return is_string($payload) && $payload !== '' ? $payload : null;
    }
}
