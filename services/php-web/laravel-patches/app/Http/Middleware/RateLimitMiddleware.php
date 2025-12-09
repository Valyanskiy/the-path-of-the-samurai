<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RateLimitMiddleware
{
    private int $maxRequests = 60;
    private int $windowSeconds = 60;

    public function handle(Request $request, Closure $next)
    {
        $ip = $request->ip();
        $key = 'rate_limit:' . $ip;

        $redis = $this->getRedis();
        if (!$redis) {
            return $next($request);
        }

        $current = (int) $redis->get($key);

        if ($current >= $this->maxRequests) {
            return response()->json([
                'ok' => false,
                'error' => [
                    'code' => 'RATE_LIMIT_EXCEEDED',
                    'message' => 'Too many requests. Please try again later.',
                ]
            ], 429);
        }

        $redis->incr($key);
        if ($current === 0) {
            $redis->expire($key, $this->windowSeconds);
        }

        $response = $next($request);
        $response->headers->set('X-RateLimit-Limit', $this->maxRequests);
        $response->headers->set('X-RateLimit-Remaining', max(0, $this->maxRequests - $current - 1));

        return $response;
    }

    private function getRedis(): ?\Redis
    {
        try {
            $redis = new \Redis();
            $redis->connect(
                getenv('REDIS_HOST') ?: 'redis',
                (int) (getenv('REDIS_PORT') ?: 6379)
            );
            return $redis;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
