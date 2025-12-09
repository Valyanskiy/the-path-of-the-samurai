<?php
namespace App\Http\Controllers;
use Illuminate\Support\Facades\DB;

class CmsController extends Controller {
    private function sanitizeHtml(?string $html): string
    {
        if ($html === null) return '';
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html);
        $html = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $html);
        $html = preg_replace('/<(iframe|object|embed|form)[^>]*>.*?<\/\1>/is', '', $html);
        $html = preg_replace('/<(iframe|object|embed|form)[^>]*\/?>/is', '', $html);
        $html = preg_replace('/\s+on\w+\s*=\s*["\'][^"\']*["\']/i', '', $html);
        $html = preg_replace('/\s+on\w+\s*=\s*[^\s>]+/i', '', $html);
        $html = preg_replace('/\b(href|src)\s*=\s*["\']?\s*javascript:[^"\'>\s]*/i', '', $html);
        return $html;
    }

    private function getCmsBlock(string $slug): ?string
    {
        try {
            $row = DB::selectOne(
                "SELECT body FROM cms_blocks WHERE slug = ? AND is_active = TRUE LIMIT 1",
                [$slug]
            );
            return $row ? $this->sanitizeHtml($row->body) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function index()
    {
        return view('cms', [
            'cmsWelcome' => $this->getCmsBlock('welcome'),
            'cmsUnsafe' => $this->getCmsBlock('unsafe'),
        ]);
    }

    public function page(string $slug) {
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $slug)) abort(404);
        
        $row = DB::selectOne(
            "SELECT title, body FROM cms_blocks WHERE slug = ? AND is_active = TRUE",
            [$slug]
        );
        if (!$row) abort(404);
        
        return response()->view('cms.page', [
            'title' => e($row->title),
            'html' => $this->sanitizeHtml($row->body)
        ]);
    }
}
