<?php

declare(strict_types=1);

namespace Mpanius\LaravelRedisGeoIp\Tests\Unit;

use Mpanius\LaravelRedisGeoIp\Support\CidrRangeParser;
use PHPUnit\Framework\TestCase;

final class CidrRangeParserTest extends TestCase
{
    public function test_it_parses_ipv4_cidr_ranges(): void
    {
        $range = CidrRangeParser::parse('1.2.3.0/24');

        self::assertSame('ipv4', $range['family']);
        self::assertSame('16909056', $range['min']);
        self::assertSame('16909311', $range['max']);
    }

    public function test_it_parses_ipv6_cidr_ranges(): void
    {
        $range = CidrRangeParser::parse('2001:db8::/126');

        self::assertSame('ipv6', $range['family']);
        self::assertSame('20010db8000000000000000000000000', $range['min']);
        self::assertSame('20010db8000000000000000000000003', $range['max']);
    }
}
