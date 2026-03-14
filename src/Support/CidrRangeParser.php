<?php

namespace Mpanius\LaravelRedisGeoIp\Support;

use InvalidArgumentException;

final class CidrRangeParser
{
    /**
     * @return array{family: string, min: string, max: string}
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
        $family = IpAddressNormalizer::detectFamily($ip);

        return $family === 'ipv4'
            ? self::parseIpv4($ip, $prefix, $cidr)
            : self::parseIpv6($ip, $prefix, $cidr);
    }

    /**
     * @return array{family: string, min: string, max: string}
     */
    private static function parseIpv4(string $ip, int $prefix, string $cidr): array
    {
        if ($prefix < 0 || $prefix > 32) {
            throw new InvalidArgumentException("IPv4 prefix for [{$cidr}] must be between 0 and 32.");
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
            'family' => 'ipv4',
            'min' => sprintf('%u', $min),
            'max' => sprintf('%u', $max),
        ];
    }

    /**
     * @return array{family: string, min: string, max: string}
     */
    private static function parseIpv6(string $ip, int $prefix, string $cidr): array
    {
        if ($prefix < 0 || $prefix > 128) {
            throw new InvalidArgumentException("IPv6 prefix for [{$cidr}] must be between 0 and 128.");
        }

        $packed = inet_pton($ip);

        if ($packed === false) {
            throw new InvalidArgumentException("Unable to parse IPv6 CIDR [{$cidr}].");
        }

        $minBytes = unpack('C*', $packed);
        $maxBytes = $minBytes;
        $remainingBits = $prefix;

        for ($index = 1; $index <= 16; $index++) {
            if ($remainingBits >= 8) {
                $remainingBits -= 8;
                continue;
            }

            if ($remainingBits <= 0) {
                $minBytes[$index] = 0;
                $maxBytes[$index] = 255;
                continue;
            }

            $mask = (0xff << (8 - $remainingBits)) & 0xff;
            $minBytes[$index] = $minBytes[$index] & $mask;
            $maxBytes[$index] = $minBytes[$index] | (~$mask & 0xff);
            $remainingBits = 0;
        }

        return [
            'family' => 'ipv6',
            'min' => self::bytesToHex($minBytes),
            'max' => self::bytesToHex($maxBytes),
        ];
    }

    /**
     * @param array<int, int> $bytes
     */
    private static function bytesToHex(array $bytes): string
    {
        $hex = '';

        foreach ($bytes as $byte) {
            $hex .= str_pad(dechex($byte), 2, '0', STR_PAD_LEFT);
        }

        return strtolower($hex);
    }
}
