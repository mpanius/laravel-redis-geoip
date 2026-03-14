<?php

namespace Mpanius\LaravelRedisGeoIp\Contracts;

use Mpanius\LaravelRedisGeoIp\Sources\DownloadedSourceFile;

interface CountryDatasetSource
{
    public function download(?string $url = null): DownloadedSourceFile;
}
