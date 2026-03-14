<?php

declare(strict_types=1);

namespace Mpanius\LaravelRedisGeoIp\Tests\Support;

use Closure;
use Mpanius\LaravelRedisGeoIp\Contracts\RedisClient;

final class FakeRedisClient implements RedisClient
{
    /** @var array<string, string> */
    public array $strings = [];

    /** @var array<string, array<string, float>> */
    public array $zsets = [];

    /** @var array<int, array{function: string, keys: array<int, string>, args: array<int, string>}> */
    public array $fcallRoCalls = [];

    /** @var array<int, array{operation: string, args: array<int, mixed>}> */
    public array $functionCalls = [];

    public ?Closure $fcallRoHandler = null;

    public function get(string $key): string|false|null
    {
        return $this->strings[$key] ?? false;
    }

    public function set(string $key, mixed $value): bool
    {
        $this->strings[$key] = (string) $value;

        return true;
    }

    public function del(string ...$keys): int|bool
    {
        $deleted = 0;

        foreach ($keys as $key) {
            $deleted += $this->deleteKey($key);
        }

        return $deleted;
    }

    public function unlink(string ...$keys): int|bool
    {
        return $this->del(...$keys);
    }

    public function multi(int $mode): static|false
    {
        return $this;
    }

    public function exec(): array|false
    {
        return [];
    }

    public function zAdd(string $key, array|float $scoreOrOptions, mixed ...$moreScoresAndMembers): mixed
    {
        if (is_array($scoreOrOptions)) {
            return 0;
        }

        $member = (string) ($moreScoresAndMembers[0] ?? '');
        $this->zsets[$key][$member] = (float) $scoreOrOptions;

        return 1;
    }

    public function zRevRange(string $key, int $start, int $stop): array|false
    {
        $entries = $this->zsets[$key] ?? [];
        $members = array_keys($entries);
        usort($members, function (string $left, string $right) use ($entries): int {
            $scoreCompare = $entries[$right] <=> $entries[$left];

            return $scoreCompare !== 0
                ? $scoreCompare
                : strcmp($right, $left);
        });

        if ($stop === -1) {
            return array_slice($members, $start);
        }

        return array_slice($members, $start, ($stop - $start) + 1);
    }

    public function zRem(string $key, mixed ...$members): int|false
    {
        $removed = 0;

        foreach ($members as $member) {
            $member = (string) $member;
            if (isset($this->zsets[$key][$member])) {
                unset($this->zsets[$key][$member]);
                $removed++;
            }
        }

        return $removed;
    }

    public function fcallRo(string $function, array $keys = [], array $args = []): mixed
    {
        $this->fcallRoCalls[] = [
            'function' => $function,
            'keys' => $keys,
            'args' => array_map(static fn ($value): string => (string) $value, $args),
        ];

        if ($this->fcallRoHandler instanceof Closure) {
            return ($this->fcallRoHandler)($function, $keys, $args);
        }

        return null;
    }

    public function redisFunction(string $operation, mixed ...$args): mixed
    {
        $this->functionCalls[] = [
            'operation' => $operation,
            'args' => $args,
        ];

        return true;
    }

    private function deleteKey(string $key): int
    {
        $deleted = 0;

        if (array_key_exists($key, $this->strings)) {
            unset($this->strings[$key]);
            $deleted++;
        }

        if (array_key_exists($key, $this->zsets)) {
            unset($this->zsets[$key]);
            $deleted++;
        }

        return $deleted;
    }
}
