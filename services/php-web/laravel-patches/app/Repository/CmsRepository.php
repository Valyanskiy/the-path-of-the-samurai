<?php

namespace App\Repository;

use Illuminate\Support\Facades\DB;

class CmsRepository
{
    public function findActiveBySlug(string $slug): ?object
    {
        return DB::selectOne(
            "SELECT slug, title, body FROM cms_blocks WHERE slug = ? AND is_active = TRUE LIMIT 1",
            [$slug]
        );
    }

    public function getBodyBySlug(string $slug): ?string
    {
        $row = $this->findActiveBySlug($slug);
        return $row?->body;
    }
}
