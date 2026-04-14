<?php
 

namespace App\Contracts\Services\Finance;

use App\DTO\Finance\AccessScope;
use App\DTO\Finance\ReportContext;
use App\DTO\Finance\ReportResult;

interface ReportsService
{
    public function listReports(AccessScope $scope): array;
    public function runReport(AccessScope $scope, string $reportKey, ReportContext $ctx): ReportResult;
    public function exportReport(AccessScope $scope, string $reportKey, ReportContext $ctx, string $format): mixed;
}