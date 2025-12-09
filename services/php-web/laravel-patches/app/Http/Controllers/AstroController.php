<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Validation\AstroRequestValidator;

class AstroController extends Controller
{
    public function index()
    {
        return view('astro');
    }

    public function events(Request $r)
    {
        $validated = new AstroRequestValidator($r);

        $from = now('UTC')->toDateString();
        $to   = now('UTC')->addDays($validated->days)->toDateString();

        $appId  = getenv('ASTRO_APP_ID') ?: '';
        $secret = getenv('ASTRO_APP_SECRET') ?: '';
        if ($appId === '' || $secret === '') {
            return response()->json(['ok' => false, 'error' => ['code' => 'CONFIG_ERROR', 'message' => 'Missing ASTRO credentials']], 500);
        }

        $auth = base64_encode($appId . ':' . $secret);
        $bodies = ['sun', 'moon'];
        $results = [];

        foreach ($bodies as $body) {
            $url = 'https://api.astronomyapi.com/api/v2/bodies/events/' . $body . '?' . http_build_query([
                'latitude'  => $validated->lat,
                'longitude' => $validated->lon,
                'from_date' => $from,
                'to_date'   => $to,
                'elevation' => $validated->elevation,
                'time'      => $validated->time,
            ]);

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => [
                    'Authorization: Basic ' . $auth,
                    'Content-Type: application/json',
                    'User-Agent: monolith-iss/1.0'
                ],
                CURLOPT_TIMEOUT        => 25,
            ]);
            $raw  = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE) ?: 0;
            curl_close($ch);

            if ($raw === false || $code >= 400) {
                $results[$body] = ['error' => true];
            } else {
                $data = json_decode($raw, true);
                $events = $data['data']['table']['rows'][0]['cells'] ?? [];
                foreach ($events as &$event) {
                    $event['body'] = $body;
                    $event['name'] = ucfirst($body);
                    $event['date'] = $event['eventHighlights']['peak']['date'] ?? null;
                }
                $results[$body] = $events;
            }
        }

        $allEvents = [];
        foreach ($results as $bodyEvents) {
            if (is_array($bodyEvents) && !isset($bodyEvents['error'])) {
                $allEvents = array_merge($allEvents, $bodyEvents);
            }
        }

        return response()->json(['ok' => true, 'events' => $allEvents, 'count' => count($allEvents)]);
    }
}
