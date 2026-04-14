<?php

namespace App\Services\Finance\Reports\Export;
use App\DTO\Finance\ReportResult;
interface ReportExporter
{
    public function export(ReportResult $result, string $format): ReportExportHandle;
}

class ReportExportHandle
{
    public function __construct(
        public string $filename,
        public string $contentType,
        public $content,  
        public string $format
    ) {}
}

class ExportFormats
{
    const CSV = 'csv';
    const PDF = 'pdf';
    const EXCEL = 'excel';
    const JSON = 'json';
}

class ReportExporters
{
    private array $exporters = [];

    public function __construct()
    {
        $this->exporters = [
            ExportFormats::CSV => CsvReportExporter::class,
            ExportFormats::PDF => PdfReportExporter::class,
            ExportFormats::EXCEL => ExcelReportExporter::class,
        ];
    }

    public function getExporter(string $format): ReportExporter
    {
        if (!isset($this->exporters[$format])) {
            throw new \InvalidArgumentException("Unsupported export format: {$format}");
        }

        return app($this->exporters[$format]);
    }

    public function supportedFormats(): array
    {
        return array_keys($this->exporters);
    }
}