<?php

namespace Mpanius\LaravelRedisGeoIp\Support;

use InvalidArgumentException;

final class IpAddressNormalizer
{
    public static function detectFamily(string $ip): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return 'ipv4';
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            return 'ipv6';
        }

        throw new InvalidArgumentException("Invalid IP address [{$ip}].");
    }

    public static function toUnsignedIntString(string $ip): string
    {
        if (self::detectFamily($ip) !== 'ipv4') {
            throw new InvalidArgumentException("IP [{$ip}] is not IPv4.");
        }

        $packed = inet_pton($ip);
        $parts = unpack('Nvalue', $packed === false ? '' : $packed);

        return sprintf('%u', $parts['value']);
    }

    public static function toFixedHexString(string $ip): string
    {
        if (self::detectFamily($ip) !== 'ipv6') {
            throw new InvalidArgumentException("IP [{$ip}] is not IPv6.");
        }

        $packed = inet_pton($ip);

        if ($packed === false) {
            throw new InvalidArgumentException("Unable to normalize IPv6 address [{$ip}].");
        }

        return strtolower(bin2hex($packed));
    }
}
