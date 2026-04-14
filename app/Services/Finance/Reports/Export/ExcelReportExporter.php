<?php

namespace App\Services\Finance\Reports\Export;

use App\DTO\Finance\ReportResult;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\FinancialReportExport;

class ExcelReportExporter implements ReportExporter
{
    public function export(ReportResult $result, string $format): ReportExportHandle
    {
        $filename = $this->generateFilename($result->reportKey, 'xlsx');
        
     
        $excelContent = Excel::raw(
            new FinancialReportExport($result),
            \Maatwebsite\Excel\Excel::XLSX
        );
        
        return new ReportExportHandle(
            filename: $filename,
            contentType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            content: $excelContent, 
            format: $format
        );
    }

    private function generateFilename(string $reportKey, string $format): string
    {
        $timestamp = now()->format('Y-m-d_H-i-s');
        $reportName = str_replace('_', '-', $reportKey);
        return "{$reportName}_{$timestamp}.{$format}";
    }
}