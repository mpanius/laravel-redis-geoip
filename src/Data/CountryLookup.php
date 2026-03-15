<?php

namespace Mpanius\LaravelRedisGeoIp\Data;

final class CountryLookup
{
    public function __construct(
        public readonly string $countryCode,
        public readonly string $continentCode,
        public readonly string $version,
        public readonly string $family,
    ) {
    }

    public static function fromRedisResponse(mixed $payload, string $family): ?self
    {
        if (!is_array($payload) || count($payload) < 3) {
            return null;
        }

        return new self(
            countryCode: (string) $payload[0],
            continentCode: (string) $payload[1],
            version: (string) $payload[2],
            family: $family,
        );
    }
}
