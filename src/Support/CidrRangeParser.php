<?php

namespace Mpanius\LaravelRedisGeoIp\Support;

use InvalidArgumentException;

final class CidrRangeParser
{
    /**
     * @return array{min: string, max: string}
     */
    public static function parse(string $cidr): array
    {
        $cidr = trim($cidr);
        $parts = explode('/', $cidr, 2);

        if (count($parts) !== 2) {
            throw new InvalidArgumentException("CIDR [{$cidr}] must contain a prefix length.");
        }

        [$ip, $prefix] = $parts;
        $prefix = (int) $prefix;

        return self::parseIpv4($ip, $prefix, $cidr);
    }

    /**
     * @return array{min: string, max: string}
     */
    private static function parseIpv4(string $ip, int $prefix, string $cidr): array
    {
        if ($prefix < 0 || $prefix > 32) {
            throw new InvalidArgumentException("IPv4 prefix for [{$cidr}] must be between 0 and 32.");
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            throw new InvalidArgumentException("CIDR [{$cidr}] must contain a valid IPv4 address.");
        }

        $packed = inet_pton($ip);

        if ($packed === false) {
            throw new InvalidArgumentException("Unable to parse IPv4 CIDR [{$cidr}].");
        }

        $value = unpack('Nvalue', $packed)['value'];
        $mask = $prefix === 0 ? 0 : ((-1 << (32 - $prefix)) & 0xffffffff);
        $min = $value & $mask;
        $max = $min | (~$mask & 0xffffffff);

        return [
            'min' => sprintf('%u', $min),
            'max' => sprintf('%u', $max),
        ];
    }
}
