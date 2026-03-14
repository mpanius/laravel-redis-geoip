<?php

namespace Mpanius\LaravelRedisGeoIp\Commands;

use DateInterval;
use DateTimeInterface;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Isolatable;
use InvalidArgumentException;
use Mpanius\LaravelRedisGeoIp\Enums\RedisGeoIpMode;
use Mpanius\LaravelRedisGeoIp\Services\SyncRedisGeoIpService;

final class SyncRedisGeoIpCommand extends Command implements Isolatable
{
    protected $signature = 'redis-geoip:sync
        {--force : Ignore the refresh interval}
        {--mode= : Override import mode (ipv4 or dual)}
        {--url= : Override the source URL for this run}
        {--keep= : Override how many versions to keep}
        {--refresh-after= : Override the refresh interval in hours}';

    protected $description = 'Download the latest country dataset and load it into Redis for native lookups.';

    public function __construct(
        private readonly SyncRedisGeoIpService $syncService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $mode = $this->option('mode');
        $keep = $this->option('keep');
        $refreshAfter = $this->option('refresh-after');

        try {
            $stats = $this->syncService->sync(
                force: (bool) $this->option('force'),
                modeOverride: $mode !== null ? RedisGeoIpMode::from((string) $mode) : null,
                sourceUrlOverride: $this->option('url') ?: null,
                keepVersionsOverride: $keep !== null ? max(1, (int) $keep) : null,
                refreshAfterHoursOverride: $refreshAfter !== null ? max(1, (int) $refreshAfter) : null,
            );
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($stats === null) {
            $this->info('Redis GeoIP dataset is fresh. Skipping sync.');

            return self::SUCCESS;
        }

        $this->info("Redis GeoIP synced version {$stats->version}.");
        $this->line("Mode: {$stats->mode->value}");
        $this->line("IPv4 ranges: {$stats->importedIpv4}");
        $this->line("IPv6 ranges: {$stats->importedIpv6}");
        $this->line("Skipped IPv6: {$stats->skippedIpv6}");
        $this->line("Source: {$stats->sourceUrl}");

        return self::SUCCESS;
    }

    public function isolationLockExpiresAt(): DateTimeInterface|DateInterval
    {
        return now()->addHour();
    }
}
