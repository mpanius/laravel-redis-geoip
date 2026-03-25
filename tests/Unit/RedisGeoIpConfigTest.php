<?php

declare(strict_types=1);

namespace Mpanius\LaravelRedisGeoIp\Tests\Unit;

use InvalidArgumentException;
use Mpanius\LaravelRedisGeoIp\Config\RedisGeoIpConfig;
use PHPUnit\Framework\TestCase;

final class RedisGeoIpConfigTest extends TestCase
{
    public function test_it_rejects_dual_mode_in_current_stable_scope(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('This release is IPv4-only.');

        RedisGeoIpConfig::fromArray([
            'mode' => 'dual',
        ]);
    }
}
