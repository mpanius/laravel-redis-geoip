<?php

declare(strict_types=1);

namespace Mpanius\LaravelRedisGeoIp\Tests\Unit;

use Mpanius\LaravelRedisGeoIp\Config\RedisGeoIpConfig;
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
        $client->fcallRoHandler = static fn (): array => ['AU', 'OC', 'v123'];

        $resolver = new RedisGeoIpResolver(
            client: $client,
            keys: new RedisGeoIpKeys('{geoip}:country'),
            config: RedisGeoIpConfig::fromArray(['mode' => 'ipv4']),
        );

        $lookup = $resolver->resolve('1.2.3.4');

        self::assertSame('AU', $lookup?->countryCode);
        self::assertSame(RedisFunctionLibrary::LOOKUP_V4, $client->fcallRoCalls[0]['function']);
        self::assertSame(['{geoip}:country:v:v123:v4'], $client->fcallRoCalls[0]['keys']);
        self::assertSame('16909060', $client->fcallRoCalls[0]['args'][0]);
        self::assertSame('v123', $client->fcallRoCalls[0]['args'][1]);
    }

    public function test_it_skips_ipv6_lookups_in_ipv4_mode(): void
    {
        $client = new FakeRedisClient();
        $resolver = new RedisGeoIpResolver(
            client: $client,
            keys: new RedisGeoIpKeys('{geoip}:country'),
            config: RedisGeoIpConfig::fromArray(['mode' => 'ipv4']),
        );

        self::assertNull($resolver->resolve('2001:db8::1'));
        self::assertSame([], $client->fcallRoCalls);
    }

    public function test_it_resolves_ipv6_addresses_in_dual_mode(): void
    {
        $client = new FakeRedisClient();
        $client->strings['{geoip}:country:active_version'] = 'v456';
        $client->fcallRoHandler = static fn (): array => ['DE', 'EU', 'v456'];

        $resolver = new RedisGeoIpResolver(
            client: $client,
            keys: new RedisGeoIpKeys('{geoip}:country'),
            config: RedisGeoIpConfig::fromArray(['mode' => 'dual']),
        );

        $lookup = $resolver->resolve('2001:db8::1');

        self::assertSame('DE', $lookup?->countryCode);
        self::assertSame(RedisFunctionLibrary::LOOKUP_V6, $client->fcallRoCalls[0]['function']);
        self::assertSame(['{geoip}:country:v:v456:v6'], $client->fcallRoCalls[0]['keys']);
        self::assertSame(
            '20010db8000000000000000000000001',
            $client->fcallRoCalls[0]['args'][0],
        );
    }
}
