<?php

namespace App\Support;

class RedisCache
{
    private ?\Redis $redis = null;

    public function __construct()
    {
        try {
            $this->redis = new \Redis();
            $this->redis->connect(
                getenv('REDIS_HOST') ?: 'redis',
                (int) (getenv('REDIS_PORT') ?: 6379)
            );
        } catch (\Throwable $e) {
            $this->redis = null;
        }
    }

    public function get(string $key): ?string
    {
        if (!$this->redis) return null;
        $val = $this->redis->get($key);
        return $val === false ? null : $val;
    }

    public function set(string $key, string $value, int $ttlSeconds): void
    {
        if (!$this->redis) return;
        $this->redis->setex($key, $ttlSeconds, $value);
    }

    public function remember(string $key, int $ttlSeconds, callable $callback): mixed
    {
        $cached = $this->get($key);
        if ($cached !== null) {
            return json_decode($cached, true);
        }
        $value = $callback();
        $this->set($key, json_encode($value), $ttlSeconds);
        return $value;
    }
}
