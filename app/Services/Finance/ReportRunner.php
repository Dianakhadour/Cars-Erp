<?php
 

namespace App\Services\Finance;

use App\DTO\Finance\AccessScope;
use App\DTO\Finance\ReportContext;
use App\DTO\Finance\ReportResult;

class ReportRunner
{
    public function __construct(
        private ReportRegistry $registry
    ) {}

    public function run(string $reportKey, ReportContext $ctx, AccessScope $scope): ReportResult
    {
        $reportDef = $this->registry->get($reportKey);
        
        if (!$reportDef) {
            throw new \InvalidArgumentException("Report not found: {$reportKey}");
        }

        $handlerClass = $reportDef['handler'];
        
        if (!class_exists($handlerClass)) {
            throw new \InvalidArgumentException("Report handler not found: {$handlerClass}");
        }

        $handler = app($handlerClass);
        
        if (!method_exists($handler, 'run')) {
            throw new \InvalidArgumentException("Report handler must implement run method: {$handlerClass}");
        }

        return $handler->run($ctx, $scope);
    }
}