<?php

declare(strict_types=1);

namespace Mpanius\LaravelRedisGeoIp\Tests\Unit;

use Mpanius\LaravelRedisGeoIp\Redis\RedisFunctionLibrary;
use PHPUnit\Framework\TestCase;

final class RedisFunctionLibraryTest extends TestCase
{
    public function test_it_registers_read_only_functions_for_both_ip_families(): void
    {
        $source = RedisFunctionLibrary::source();

        self::assertStringContainsString("#!lua name=" . RedisFunctionLibrary::LIBRARY, $source);
        self::assertStringContainsString("function_name='" . RedisFunctionLibrary::LOOKUP_V4 . "'", $source);
        self::assertStringContainsString("function_name='" . RedisFunctionLibrary::LOOKUP_V6 . "'", $source);
        self::assertStringContainsString("flags={'no-writes'}", $source);
    }
}
