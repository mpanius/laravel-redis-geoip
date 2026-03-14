<?php

declare(strict_types=1);

$autoloadCandidates = [
    dirname(__DIR__) . '/vendor/autoload.php',
    dirname(__DIR__, 3) . '/vendor/autoload.php',
];

foreach ($autoloadCandidates as $autoload) {
    if (is_file($autoload)) {
        require $autoload;
        break;
    }
}

spl_autoload_register(static function (string $class): void {
    $mappings = [
        'Mpanius\\LaravelRedisGeoIp\\Tests\\' => dirname(__DIR__) . '/tests/',
        'Mpanius\\LaravelRedisGeoIp\\' => dirname(__DIR__) . '/src/',
    ];

    foreach ($mappings as $prefix => $basePath) {
        if (!str_starts_with($class, $prefix)) {
            continue;
        }

        $relative = substr($class, strlen($prefix));
        $path = $basePath . str_replace('\\', '/', $relative) . '.php';

        if (is_file($path)) {
            require $path;
            return;
        }
    }
});
