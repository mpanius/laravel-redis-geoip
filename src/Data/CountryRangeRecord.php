<?php

namespace Mpanius\LaravelRedisGeoIp\Data;

use Mpanius\LaravelRedisGeoIp\Support\CidrRangeParser;

final class CountryRangeRecord
{
    public function __construct(
        public readonly string $network,
        public readonly string $country,
        public readonly string $countryCode,
        public readonly string $continentCode,
        public readonly string $family,
        public readonly string $min,
        public readonly string $max,
    ) {
    }

    public static function fromCsv(
        string $network,
        string $country,
        string $countryCode,
        string $continentCode,
    ): self {
        $range = CidrRangeParser::parse($network);

        return new self(
            network: $network,
            country: self::sanitizeText($country),
            countryCode: strtoupper(trim($countryCode)),
            continentCode: strtoupper(trim($continentCode)),
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
                $this->continentCode,
                $this->country,
            ]);
        }

        return implode("\t", [
            $this->max,
            $this->min,
            $this->countryCode,
            $this->continentCode,
            $this->country,
        ]);
    }

    private static function sanitizeText(string $value): string
    {
        $value = str_replace(["\r", "\n", "\t"], ' ', trim($value));

        return preg_replace('/\s+/', ' ', $value) ?? $value;
    }
}
