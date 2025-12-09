<?php

namespace App\Support;

class HtmlSanitizer
{
    public function sanitize(?string $html): string
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
}
