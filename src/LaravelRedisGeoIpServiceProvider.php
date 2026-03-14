<?php

namespace Mpanius\LaravelRedisGeoIp;

use Illuminate\Support\Facades\Redis;
use RuntimeException;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Mpanius\LaravelRedisGeoIp\Commands\SyncRedisGeoIpCommand;
use Mpanius\LaravelRedisGeoIp\Config\RedisGeoIpConfig;
use Mpanius\LaravelRedisGeoIp\Contracts\CountryDatasetSource;
use Mpanius\LaravelRedisGeoIp\Contracts\CountryResolver;
use Mpanius\LaravelRedisGeoIp\Contracts\RedisClient;
use Mpanius\LaravelRedisGeoIp\Redis\PhpRedisClient;
use Mpanius\LaravelRedisGeoIp\Redis\RedisGeoIpKeys;
use Mpanius\LaravelRedisGeoIp\Redis\RedisGeoIpStore;
use Mpanius\LaravelRedisGeoIp\Resolvers\RedisGeoIpResolver;
use Mpanius\LaravelRedisGeoIp\Services\SyncRedisGeoIpService;
use Mpanius\LaravelRedisGeoIp\Sources\IplocateCountryCsvSource;

final class LaravelRedisGeoIpServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-redis-geoip')
            ->hasConfigFile()
            ->hasCommand(SyncRedisGeoIpCommand::class);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(RedisGeoIpConfig::class, function ($app): RedisGeoIpConfig {
            return RedisGeoIpConfig::fromArray($app['config']->get('redis-geoip', []));
        });

        $this->app->singleton(RedisGeoIpKeys::class, function ($app): RedisGeoIpKeys {
            return new RedisGeoIpKeys($app->make(RedisGeoIpConfig::class)->prefix());
        });

        $this->app->singleton(RedisClient::class, function ($app): RedisClient {
            $connectionName = $app->make(RedisGeoIpConfig::class)->redisConnection();
            $client = Redis::connection($connectionName)->client();

            if (!$client instanceof \Redis) {
                throw new RuntimeException('laravel-redis-geoip requires the phpredis client.');
            }

            return new PhpRedisClient($client);
        });

        $this->app->singleton(CountryDatasetSource::class, function ($app): CountryDatasetSource {
            return new IplocateCountryCsvSource($app->make(RedisGeoIpConfig::class));
        });

        $this->app->singleton(RedisGeoIpStore::class, function ($app): RedisGeoIpStore {
            $config = $app->make(RedisGeoIpConfig::class);

            return new RedisGeoIpStore(
                client: $app->make(RedisClient::class),
                keys: $app->make(RedisGeoIpKeys::class),
                batchSize: $config->batchSize(),
            );
        });

        $this->app->singleton(SyncRedisGeoIpService::class, function ($app): SyncRedisGeoIpService {
            return new SyncRedisGeoIpService(
                store: $app->make(RedisGeoIpStore::class),
                source: $app->make(CountryDatasetSource::class),
                config: $app->make(RedisGeoIpConfig::class),
            );
        });

        $this->app->singleton(CountryResolver::class, function ($app): CountryResolver {
            return new RedisGeoIpResolver(
                client: $app->make(RedisClient::class),
                keys: $app->make(RedisGeoIpKeys::class),
                config: $app->make(RedisGeoIpConfig::class),
            );
        });

        $this->app->alias(CountryResolver::class, RedisGeoIpResolver::class);
    }
}
