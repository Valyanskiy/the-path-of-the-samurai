<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class ProxyController extends Controller
{
    private function base(): string {
        return getenv('RUST_BASE') ?: 'http://rust_iss:3000';
    }

    public function last()  { return $this->pipe('/last'); }

    public function trend() {
        // Безопасная передача параметров: только разрешённые ключи
        $allowed = ['from', 'to', 'limit'];
        $params = [];
        foreach ($allowed as $key) {
            $val = request()->query($key);
            if ($val !== null) {
                // Валидация: только числа и даты
                if (preg_match('/^[\d\-:TZ]+$/', (string)$val)) {
                    $params[$key] = $val;
                }
            }
        }
        $qs = $params ? '?' . http_build_query($params) : '';
        return $this->pipe('/iss/trend' . $qs);
    }

    private function pipe(string $path)
    {
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
            return new Response($body, 200, ['Content-Type' => 'application/json']);
        } catch (\Throwable $e) {
            return new Response('{"error":"upstream"}', 200, ['Content-Type' => 'application/json']);
        }
    }
}
