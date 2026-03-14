<?php

namespace Mpanius\LaravelRedisGeoIp\Redis;

use Mpanius\LaravelRedisGeoIp\Contracts\RedisClient;

final class PhpRedisClient implements RedisClient
{
    public function __construct(
        private readonly \Redis $client,
    ) {
    }

    public function get(string $key): string|false|null
    {
        return $this->client->get($key);
    }

    public function set(string $key, mixed $value): bool
    {
        return $this->client->set($key, $value);
    }

    public function del(string ...$keys): int|bool
    {
        return $this->client->del(...$keys);
    }

    public function unlink(string ...$keys): int|bool
    {
        return $this->client->unlink(...$keys);
    }

    public function multi(int $mode): static|false
    {
        $result = $this->client->multi($mode);

        return $result === false ? false : $this;
    }

    public function exec(): array|false
    {
        return $this->client->exec();
    }

    public function zAdd(string $key, array|float $scoreOrOptions, mixed ...$moreScoresAndMembers): mixed
    {
        return $this->client->zAdd($key, $scoreOrOptions, ...$moreScoresAndMembers);
    }

    public function zRevRange(string $key, int $start, int $stop): array|false
    {
        return $this->client->zRevRange($key, $start, $stop);
    }

    public function zRem(string $key, mixed ...$members): int|false
    {
        return $this->client->zRem($key, ...$members);
    }

    public function fcallRo(string $function, array $keys = [], array $args = []): mixed
    {
        return $this->client->fcall_ro($function, $keys, $args);
    }

    public function redisFunction(string $operation, mixed ...$args): mixed
    {
        return $this->client->function($operation, ...$args);
    }
}
