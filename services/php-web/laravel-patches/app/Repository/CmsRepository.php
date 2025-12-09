<?php

namespace App\Repository;

use Illuminate\Support\Facades\DB;
use App\Support\RedisCache;

class CmsRepository
{
    private const CACHE_TTL = 3600; // 1 hour
    private RedisCache $cache;

    public function __construct()
    {
        $this->cache = new RedisCache();
    }

    public function findActiveBySlug(string $slug): ?object
    {
        $key = 'cms:' . $slug;
        $data = $this->cache->remember($key, self::CACHE_TTL, function () use ($slug) {
            $row = DB::selectOne(
                "SELECT slug, title, body FROM cms_blocks WHERE slug = ? AND is_active = TRUE LIMIT 1",
                [$slug]
            );
            return $row ? (array) $row : null;
        });
        return $data ? (object) $data : null;
    }

    public function getBodyBySlug(string $slug): ?string
    {
        $row = $this->findActiveBySlug($slug);
        return $row?->body;
    }
}
