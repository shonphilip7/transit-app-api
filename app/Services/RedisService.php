<?php

namespace App\Services;

use Redis;
use RuntimeException;

class RedisService
{
    private ?Redis $client = null;

    /**
     * Create a new class instance.
     */
    public function __construct(
        private readonly ?string $host = null,
        private readonly ?int $port = null,
        private readonly ?string $password = null,
        private readonly ?int $database = null,
    ) {}

    public function connect(): void
    {
        if ($this->isConnected()) {
            return;
        }
        $host = $this->host ?? (string) config('database.redis.default.host', '127.0.0.1');
        $port = $this->port ?? (int) config('database.redis.default.port', 6379);
        $password = $this->password ?? config('database.redis.default.password');
        $database = $this->database ?? (int) config('database.redis.default.database', 0);

        $client = new Redis;
        if (! $client->connect($host, $port)) {
            throw new RuntimeException("Unable to connect to Redis at {$host}:{$port}.");
        }
        if ($password !== null && $password !== '') {
            $client->auth($password);
        }
        $client->select($database);
        $this->client = $client;
    }

    public function set(string $key, mixed $value, ?int $ttlSeconds = null): bool
    {
        $this->connect();
        $payload = is_string($value) ? $value : json_encode($value, JSON_THROW_ON_ERROR);
        if ($ttlSeconds !== null) {
            return $this->client->setex($key, $ttlSeconds, $payload);
        }

        return $this->client->set($key, $payload);
    }

    public function get(string $key): mixed
    {
        $this->connect();
        $value = $this->client->get($key);
        if ($value === false) {
            return null;
        }
        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }

    private function isConnected(): bool
    {
        return $this->client instanceof Redis && $this->client->isConnected();
    }
}
