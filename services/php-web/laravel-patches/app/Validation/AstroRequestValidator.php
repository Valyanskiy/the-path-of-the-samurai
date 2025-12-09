<?php

namespace App\Validation;

use Illuminate\Http\Request;

class AstroRequestValidator
{
    public float $lat;
    public float $lon;
    public int $elevation;
    public int $days;
    public string $time;

    public function __construct(Request $request)
    {
        $this->lat = max(-90.0, min(90.0, (float) $request->query('lat', 55.7558)));
        $this->lon = max(-180.0, min(180.0, (float) $request->query('lon', 37.6176)));
        $this->elevation = max(0, min(10000, (int) $request->query('elevation', 0)));
        $this->days = max(1, min(30, (int) $request->query('days', 7)));
        $this->time = $request->query('time') ? $request->query('time') . ':00' : now('UTC')->format('H:i:s');
    }
}
