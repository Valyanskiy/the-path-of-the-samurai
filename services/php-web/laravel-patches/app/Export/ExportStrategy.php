<?php
/**
 * Strategy Pattern (GoF) - интерфейс стратегии экспорта
 * 
 * Позволяет менять алгоритм экспорта данных независимо от клиента.
 */

namespace App\Export;

interface ExportStrategy
{
    public function getContentType(): string;
    public function getFilename(): string;
    public function writeHeader($handle): void;
    public function writeRow($handle, object $row): void;
}
