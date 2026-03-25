<?php

declare(strict_types=1);

namespace Mpanius\LaravelRedisGeoIp\Tests\Unit;

use Mpanius\LaravelRedisGeoIp\Redis\RedisFunctionLibrary;
use Mpanius\LaravelRedisGeoIp\Redis\RedisGeoIpKeys;
use Mpanius\LaravelRedisGeoIp\Resolvers\RedisGeoIpResolver;
use Mpanius\LaravelRedisGeoIp\Tests\Support\FakeRedisClient;
use PHPUnit\Framework\TestCase;

final class RedisGeoIpResolverTest extends TestCase
{
    public function test_it_resolves_ipv4_addresses_via_fcall_ro(): void
    {
        $client = new FakeRedisClient();
        $client->strings['{geoip}:country:active_version'] = 'v123';
        $client->fcallRoHandler = static fn (): string => 'AU';

        $resolver = new RedisGeoIpResolver(
            client: $client,
            keys: new RedisGeoIpKeys('{geoip}:country'),
        );

        $lookup = $resolver->resolve('1.2.3.4');

        self::assertSame('AU', $lookup);
        self::assertSame(RedisFunctionLibrary::LOOKUP_V4, $client->fcallRoCalls[0]['function']);
        self::assertSame(['{geoip}:country:v:v123:v4'], $client->fcallRoCalls[0]['keys']);
        self::assertSame('16909060', $client->fcallRoCalls[0]['args'][0]);
    }

    public function test_it_returns_null_for_unsupported_non_ipv4_addresses(): void
    {
        $client = new FakeRedisClient();
        $client->strings['{geoip}:country:active_version'] = 'v456';

        $resolver = new RedisGeoIpResolver(
            client: $client,
            keys: new RedisGeoIpKeys('{geoip}:country'),
        );

        self::assertNull($resolver->resolve('2001:db8::1'));
        self::assertSame([], $client->fcallRoCalls);
    }

    public function test_it_returns_null_for_invalid_ip_addresses(): void
    {
        $client = new FakeRedisClient();
        $client->strings['{geoip}:country:active_version'] = 'v789';

        $resolver = new RedisGeoIpResolver(
            client: $client,
            keys: new RedisGeoIpKeys('{geoip}:country'),
        );

        self::assertNull($resolver->resolve('not-an-ip'));
        self::assertSame([], $client->fcallRoCalls);
    }
}
