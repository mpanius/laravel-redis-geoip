<?php

namespace Mpanius\LaravelRedisGeoIp\Support;

use InvalidArgumentException;

final class IpAddressNormalizer
{
    public static function toUnsignedIntString(string $ip): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            throw new InvalidArgumentException("IP [{$ip}] is not a valid IPv4 address.");
        }

        $packed = inet_pton($ip);
        $parts = unpack('Nvalue', $packed === false ? '' : $packed);

        return sprintf('%u', $parts['value']);
    }
}
