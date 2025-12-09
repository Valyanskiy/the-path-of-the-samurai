<?php

namespace App\Export;

class ExcelExportStrategy implements ExportStrategy
{
    public function getContentType(): string
    {
        return 'application/vnd.ms-excel';
    }

    public function getFilename(): string
    {
        return 'telemetry_legacy.xls';
    }

    public function writeHeader($handle): void
    {
        fwrite($handle, "id\trecorded_at\tvoltage\ttemp\tsource_file\n");
    }

    public function writeRow($handle, object $row): void
    {
        fwrite($handle, "{$row->id}\t{$row->recorded_at}\t{$row->voltage}\t{$row->temp}\t{$row->source_file}\n");
    }
}
