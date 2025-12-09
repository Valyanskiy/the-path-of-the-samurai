<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use App\Validation\ProxyRequestValidator;
use App\Support\RedisCache;

class ProxyController extends Controller
{
    private const CACHE_TTL = 120; // 2 minutes
    private RedisCache $cache;

    public function __construct()
    {
        $this->cache = new RedisCache();
    }

    private function base(): string {
        return getenv('RUST_BASE') ?: 'http://rust_iss:3000';
    }

    public function last() { return $this->pipe('/last'); }

    public function trend() {
        $validated = new ProxyRequestValidator(request());
        return $this->pipe('/iss/trend' . $validated->toQueryString());
    }

    private function pipe(string $path)
    {
        $cacheKey = 'api:iss:' . md5($path);
        
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return new Response($cached, 200, ['Content-Type' => 'application/json', 'X-Cache' => 'HIT']);
        }

        $url = $this->base() . $path;
        try {
            $ctx = stream_context_create([
                'http' => ['timeout' => 5, 'ignore_errors' => true],
            ]);
            $body = @file_get_contents($url, false, $ctx);
            if ($body === false || trim($body) === '') {
                $body = '{}';
            }
            json_decode($body);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $body = '{}';
            }
            
            $this->cache->set($cacheKey, $body, self::CACHE_TTL);
            
            return new Response($body, 200, ['Content-Type' => 'application/json', 'X-Cache' => 'MISS']);
        } catch (\Throwable $e) {
            return new Response('{"ok":false,"error":{"code":"UPSTREAM_ERROR","message":"Service unavailable"}}', 200, ['Content-Type' => 'application/json']);
        }
    }
}
