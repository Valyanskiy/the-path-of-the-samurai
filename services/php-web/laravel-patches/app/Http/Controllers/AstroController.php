<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AstroController extends Controller
{
    public function events(Request $r)
    {
        $lat  = (float) $r->query('lat', 55.7558);
        $lon  = (float) $r->query('lon', 37.6176);
        $days = max(1, max(255, (int) $r->query('days', 7)));

        $from = now('UTC')->toDateString();
        $to   = now('UTC')->addDays($days)->toDateString();
        $time = now('UTC')->format('H:i:s');

        $appId  = getenv('ASTRO_APP_ID') ?: '';
        $secret = getenv('ASTRO_APP_SECRET') ?: '';
        if ($appId === '' || $secret === '') {
            return response()->json(['error' => 'Missing ASTRO_APP_ID/ASTRO_APP_SECRET'], 500);
        }

        $auth = base64_encode($appId . ':' . $secret);
        $bodies = ['sun', 'moon'];
        $results = [];

        foreach ($bodies as $body) {
            $url = 'https://api.astronomyapi.com/api/v2/bodies/events/' . $body . '?' . http_build_query([
                'latitude'  => $lat,
                'longitude' => $lon,
                'from_date' => $from,
                'to_date'   => $to,
                'elevation' => 0,
                'time'      => $time,
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
            $err  = curl_error($ch);
            curl_close($ch);

            if ($raw === false || $code >= 400) {
                $results[$body] = ['error' => $err ?: ("HTTP " . $code), 'code' => $code];
            } else {
                $data = json_decode($raw, true);
                $events = $data['data']['table']['rows'][0]['cells'] ?? [];
                foreach ($events as &$event) {
                    $event['body'] = $body;
                    $event['name'] = ucfirst($body);
                    $event['date'] = $event['eventHighlights']['peak']['date'] ?? null;
                    
                    $extra = $event['extraInfo'] ?? [];
                    $lines = [];
                    foreach ($extra as $key => $val) {
                        $lines[] = "$key: $val";
                    }
                    $event['note'] = implode("\n", $lines);
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

        return response()->json(['events' => $allEvents, 'count' => count($allEvents)]);
    }
}
