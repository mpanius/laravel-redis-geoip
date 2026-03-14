<?php

namespace Mpanius\LaravelRedisGeoIp\Contracts;

interface RedisClient
{
    public function get(string $key): string|false|null;

    public function set(string $key, mixed $value): bool;

    public function del(string ...$keys): int|bool;

    public function unlink(string ...$keys): int|bool;

    public function multi(int $mode): static|false;

    public function exec(): array|false;

    public function zAdd(string $key, array|float $scoreOrOptions, mixed ...$moreScoresAndMembers): mixed;

    public function zRevRange(string $key, int $start, int $stop): array|false;

    public function zRem(string $key, mixed ...$members): int|false;

    public function fcallRo(string $function, array $keys = [], array $args = []): mixed;

    public function redisFunction(string $operation, mixed ...$args): mixed;
}
