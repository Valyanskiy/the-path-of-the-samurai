<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Export\ExportStrategy;
use App\Export\CsvExportStrategy;
use App\Export\ExcelExportStrategy;

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

    private function export(ExportStrategy $strategy): StreamedResponse
    {
        return response()->streamDownload(function () use ($strategy) {
            $out = fopen('php://output', 'w');
            $strategy->writeHeader($out);
            DB::table('telemetry_legacy')->orderBy('id')->chunk(500, function ($rows) use ($out, $strategy) {
                foreach ($rows as $row) {
                    $strategy->writeRow($out, $row);
                }
            });
            fclose($out);
        }, $strategy->getFilename(), ['Content-Type' => $strategy->getContentType()]);
    }

    public function exportCsv(): StreamedResponse
    {
        return $this->export(new CsvExportStrategy());
    }

    public function exportExcel(): StreamedResponse
    {
        return $this->export(new ExcelExportStrategy());
    }
}
