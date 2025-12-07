<?php
namespace App\Http\Controllers;
use Illuminate\Support\Facades\DB;

class CmsController extends Controller {
    /**
     * Санитизация HTML: удаление опасных тегов и атрибутов
     */
    private function sanitizeHtml(?string $html): string
    {
        if ($html === null) {
            return '';
        }
        // Удаляем script, style, iframe, object, embed теги
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html);
        $html = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $html);
        $html = preg_replace('/<(iframe|object|embed|form)[^>]*>.*?<\/\1>/is', '', $html);
        $html = preg_replace('/<(iframe|object|embed|form)[^>]*\/?>/is', '', $html);
        // Удаляем on* атрибуты (onclick, onerror и т.д.)
        $html = preg_replace('/\s+on\w+\s*=\s*["\'][^"\']*["\']/i', '', $html);
        $html = preg_replace('/\s+on\w+\s*=\s*[^\s>]+/i', '', $html);
        // Удаляем javascript: в href/src
        $html = preg_replace('/\b(href|src)\s*=\s*["\']?\s*javascript:[^"\'>\s]*/i', '', $html);
        return $html;
    }

    public function page(string $slug) {
        // Валидация slug: только буквы, цифры, дефис, подчёркивание
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $slug)) {
            abort(404);
        }
        
        $row = DB::selectOne(
            "SELECT title, body FROM cms_blocks WHERE slug = ? AND is_active = TRUE",
            [$slug]
        );
        if (!$row) abort(404);
        
        return response()->view('cms.page', [
            'title' => e($row->title), // экранируем title
            'html' => $this->sanitizeHtml($row->body) // санитизируем body
        ]);
    }
}
