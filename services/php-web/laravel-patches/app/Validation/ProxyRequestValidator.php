<?php

namespace App\Validation;

use Illuminate\Http\Request;

class ProxyRequestValidator
{
    private array $allowedKeys = ['from', 'to', 'limit'];
    public array $params = [];

    public function __construct(Request $request)
    {
        foreach ($this->allowedKeys as $key) {
            $val = $request->query($key);
            if ($val !== null && preg_match('/^[\d\-:TZ]+$/', (string) $val)) {
                $this->params[$key] = $val;
            }
        }
    }

    public function toQueryString(): string
    {
        return $this->params ? '?' . http_build_query($this->params) : '';
    }
}
