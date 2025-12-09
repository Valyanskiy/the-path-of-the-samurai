<?php

namespace App\Export;

class CsvExportStrategy implements ExportStrategy
{
    public function getContentType(): string
    {
        return 'text/csv';
    }

    public function getFilename(): string
    {
        return 'telemetry_legacy.csv';
    }

    public function writeHeader($handle): void
    {
        fputcsv($handle, ['id', 'recorded_at', 'voltage', 'temp', 'source_file']);
    }

    public function writeRow($handle, object $row): void
    {
        fputcsv($handle, [$row->id, $row->recorded_at, $row->voltage, $row->temp, $row->source_file]);
    }
}
