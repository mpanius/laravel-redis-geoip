<?php

namespace Mpanius\LaravelRedisGeoIp\Enums;

enum RedisGeoIpMode: string
{
    case Ipv4 = 'ipv4';
    case Dual = 'dual';

    public function supportsIpv6(): bool
    {
        return $this === self::Dual;
    }
}
