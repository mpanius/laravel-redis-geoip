<?php

namespace Mpanius\LaravelRedisGeoIp\Data;

use Mpanius\LaravelRedisGeoIp\Support\CidrRangeParser;

final class CountryRangeRecord
{
    public function __construct(
        public readonly string $network,
        public readonly string $countryCode,
        public readonly string $min,
        public readonly string $max,
    ) {
    }

    public static function fromCsv(
        string $network,
        string $countryCode,
    ): self {
        $range = CidrRangeParser::parse($network);

        return new self(
            network: $network,
            countryCode: strtoupper(trim($countryCode)),
            min: $range['min'],
            max: $range['max'],
        );
    }

    public function score(): float
    {
        return (float) $this->max;
    }

    public function toRedisMember(): string
    {
        return implode("\t", [
            $this->min,
            $this->countryCode,
        ]);
    }
}
