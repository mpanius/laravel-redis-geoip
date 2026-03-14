<?php

namespace Mpanius\LaravelRedisGeoIp\Contracts;

use Mpanius\LaravelRedisGeoIp\Data\CountryLookup;

interface CountryResolver
{
    public function resolve(string $ip): ?CountryLookup;
}
