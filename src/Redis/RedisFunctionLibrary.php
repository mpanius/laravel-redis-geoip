<?php

namespace Mpanius\LaravelRedisGeoIp\Redis;

final class RedisFunctionLibrary
{
    public const LIBRARY = 'laravel_redis_geoip';
    public const LOOKUP_V4 = 'geoip_country_lookup_v4';
    public const LOOKUP_V6 = 'geoip_country_lookup_v6';

    public static function source(): string
    {
        return <<<'LUA'
#!lua name=laravel_redis_geoip

redis.register_function{
    function_name='geoip_country_lookup_v4',
    flags={'no-writes'},
    callback=function(keys, args)
        local hits = redis.call('ZRANGE', keys[1], args[1], '+inf', 'BYSCORE', 'LIMIT', 0, 1)

        if #hits == 0 then
            return false
        end

        local min_ip, country_code = string.match(hits[1], '^(%d+)\t([^\t]*)$')
        if not min_ip or tonumber(min_ip) > tonumber(args[1]) then
            return false
        end

        return country_code
    end
}

redis.register_function{
    function_name='geoip_country_lookup_v6',
    flags={'no-writes'},
    callback=function(keys, args)
        local lower = '[' .. args[1]
        local hits = redis.call('ZRANGE', keys[1], lower, '+', 'BYLEX', 'LIMIT', 0, 1)

        if #hits == 0 then
            return false
        end

        local max_ip, min_ip, country_code = string.match(hits[1], '^(%x+)\t(%x+)\t([^\t]*)$')
        if not min_ip or min_ip > args[1] then
            return false
        end

        return country_code
    end
}
LUA;
    }
}
