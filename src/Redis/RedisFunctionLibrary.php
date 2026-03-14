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

local function active_version(active_version_key)
    return redis.call('GET', active_version_key)
end

local function dataset_key(prefix, version, suffix)
    return prefix .. ':v:' .. version .. ':' .. suffix
end

redis.register_function{
    function_name='geoip_country_lookup_v4',
    flags={'no-writes'},
    callback=function(keys, args)
        local version = active_version(keys[1])
        if not version then
            return false
        end

        local key = dataset_key(args[1], version, 'v4')
        local hits = redis.call('ZRANGE', key, args[2], '+inf', 'BYSCORE', 'LIMIT', 0, 1)

        if #hits == 0 then
            return false
        end

        local min_ip, country_code, continent_code, country = string.match(hits[1], '^(%d+)\t([^\t]*)\t([^\t]*)\t(.*)$')
        if not min_ip or tonumber(min_ip) > tonumber(args[2]) then
            return false
        end

        return {country_code, continent_code, country, version}
    end
}

redis.register_function{
    function_name='geoip_country_lookup_v6',
    flags={'no-writes'},
    callback=function(keys, args)
        local version = active_version(keys[1])
        if not version then
            return false
        end

        local key = dataset_key(args[1], version, 'v6')
        local lower = '[' .. args[2]
        local hits = redis.call('ZRANGE', key, lower, '+', 'BYLEX', 'LIMIT', 0, 1)

        if #hits == 0 then
            return false
        end

        local max_ip, min_ip, country_code, continent_code, country = string.match(hits[1], '^(%x+)\t(%x+)\t([^\t]*)\t([^\t]*)\t(.*)$')
        if not min_ip or min_ip > args[2] then
            return false
        end

        return {country_code, continent_code, country, version}
    end
}
LUA;
    }
}
