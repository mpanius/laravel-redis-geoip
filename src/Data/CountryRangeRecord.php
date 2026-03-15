<?php

namespace Mpanius\LaravelRedisGeoIp\Data;

use Mpanius\LaravelRedisGeoIp\Support\CidrRangeParser;

final class CountryRangeRecord
{
    public function __construct(
        public readonly string $network,
        public readonly string $countryCode,
        public readonly string $family,
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
            family: $range['family'],
            min: $range['min'],
            max: $range['max'],
        );
    }

    public function isIpv4(): bool
    {
        return $this->family === 'ipv4';
    }

    public function isIpv6(): bool
    {
        return $this->family === 'ipv6';
    }

    public function score(): float
    {
        return (float) $this->max;
    }

    public function toRedisMember(): string
    {
        if ($this->isIpv4()) {
            return implode("\t", [
                $this->min,
                $this->countryCode,
            ]);
        }

        return implode("\t", [
            $this->max,
            $this->min,
            $this->countryCode,
        ]);
    }
}
