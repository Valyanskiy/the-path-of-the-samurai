<?php

namespace App\Validation;

use Illuminate\Http\Request;

class OsdrRequestValidator
{
    public int $limit;

    public function __construct(Request $request)
    {
        $this->limit = max(1, min(100, (int) $request->query('limit', 20)));
    }
}
