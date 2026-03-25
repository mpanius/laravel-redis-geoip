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

        self::assertSame('16909056', $range['min']);
        self::assertSame('16909311', $range['max']);
    }

    public function test_it_rejects_non_ipv4_cidr_ranges(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        CidrRangeParser::parse('2001:db8::/126');
    }
}
