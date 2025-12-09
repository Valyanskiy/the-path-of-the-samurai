<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TelemetryController extends Controller
{
    public function index()
    {
        $items = DB::table('telemetry_legacy')
            ->orderByDesc('recorded_at')
            ->limit(500)
            ->get();

        return view('telemetry', ['items' => $items]);
    }

    public function exportCsv(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'recorded_at', 'voltage', 'temp', 'source_file']);
            DB::table('telemetry_legacy')->orderBy('id')->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $r) {
                    fputcsv($out, [$r->id, $r->recorded_at, $r->voltage, $r->temp, $r->source_file]);
                }
            });
            fclose($out);
        }, 'telemetry_legacy.csv', ['Content-Type' => 'text/csv']);
    }

    public function exportExcel(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            // TSV format opens in Excel
            fwrite($out, "id\trecorded_at\tvoltage\ttemp\tsource_file\n");
            DB::table('telemetry_legacy')->orderBy('id')->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $r) {
                    fwrite($out, "{$r->id}\t{$r->recorded_at}\t{$r->voltage}\t{$r->temp}\t{$r->source_file}\n");
                }
            });
            fclose($out);
        }, 'telemetry_legacy.xls', ['Content-Type' => 'application/vnd.ms-excel']);
    }
}
