<?php

namespace Mpanius\LaravelRedisGeoIp\Contracts;

interface CountryResolver
{
    public function resolve(string $ip): ?string;
}
