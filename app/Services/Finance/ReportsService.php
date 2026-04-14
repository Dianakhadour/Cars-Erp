<?php

namespace App\Services\Finance;

use App\DTO\Finance\AccessScope;
use App\DTO\Finance\ReportResult;
use App\DTO\Finance\ReportContext;
use Illuminate\Support\Facades\DB;
use App\Services\Finance\ReportRunner;
use App\Services\Finance\ReportRegistry;
use App\Services\Finance\PermissionGuard;
 
use App\Services\Finance\Reports\Export\ReportExporters;
use App\Contracts\Services\Finance\ReportsService as ReportsServiceInterface;

class ReportsService implements ReportsServiceInterface
{
    public function __construct(
        private ReportRegistry $registry,
        private ReportRunner $runner,
        private PermissionGuard $guard,
        private ReportExporters $exporters
    ) {}

    public function listReports(AccessScope $scope): array
    {
        $all = $this->registry->all();
        return array_values(array_filter($all, function($def) use ($scope) {
            return $this->guard->canViewReport($scope, $def['key']);
        }));
    }

    public function runReport(AccessScope $scope, string $reportKey, ReportContext $ctx): ReportResult
    {
        // Permission check
        $this->guard->requireViewReport($scope, $reportKey);
        
        // Branch filtering
        $ctx->branchIds = $this->guard->intersectBranches($scope->allowedBranchIds, $ctx->branchIds);
        
        // Normalize context
        $ctx = $this->normalizeContext($ctx);

       
        return $this->runner->run($reportKey, $ctx, $scope);
    }

    public function exportReport(AccessScope $scope, string $reportKey, ReportContext $ctx, string $format): mixed
    {
        $result = $this->runReport($scope, $reportKey, $ctx);
        $exporter = $this->exporters->getExporter($format);
        return $exporter->export($result, $format);
    }

    private function normalizeContext(ReportContext $ctx): ReportContext
    {
        if (!$ctx->from && !$ctx->to && $ctx->asOf) {
            $ctx->from = $ctx->asOf;
            $ctx->to = $ctx->asOf;
        }
        
        if (!$ctx->from) {
            $ctx->from = now()->startOfMonth()->format('Y-m-d');
        }
        
        if (!$ctx->to) {
            $ctx->to = now()->format('Y-m-d');
        }

        return $ctx;
    }

    private function export(ReportResult $result, string $format): mixed
    {
        // We'll implement export in the next steps
        return $result;
    }
}