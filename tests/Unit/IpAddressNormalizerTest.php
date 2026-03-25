<?php

declare(strict_types=1);

namespace Mpanius\LaravelRedisGeoIp\Tests\Unit;

use InvalidArgumentException;
use Mpanius\LaravelRedisGeoIp\Support\IpAddressNormalizer;
use PHPUnit\Framework\TestCase;

final class IpAddressNormalizerTest extends TestCase
{
    public function test_it_normalizes_ipv4_addresses(): void
    {
        self::assertSame('16909060', IpAddressNormalizer::toUnsignedIntString('1.2.3.4'));
    }

    public function test_it_rejects_non_ipv4_addresses(): void
    {
        $this->expectException(InvalidArgumentException::class);

        IpAddressNormalizer::toUnsignedIntString('2001:db8::1');
    }

    public function test_it_rejects_invalid_ip_addresses(): void
    {
        $this->expectException(InvalidArgumentException::class);

        IpAddressNormalizer::toUnsignedIntString('not-an-ip');
    }
}
