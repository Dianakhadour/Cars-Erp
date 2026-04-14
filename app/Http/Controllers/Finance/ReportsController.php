<?php

namespace App\Http\Controllers\Finance;

use Illuminate\Http\Request;
use App\DTO\Finance\AccessScope;
use Illuminate\Http\JsonResponse;
use App\DTO\Finance\ReportContext;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Models\Tenant\JournalEntryRow;
use App\Contracts\Services\Finance\ReportsService;

class ReportsController extends Controller
{
    public function __construct(
        private ReportsService $reportsService
    ) {}

    /**
     * GET /api/finance/reports
     */
    public function listReports(Request $request): JsonResponse
    {
        try {
            $scope = $this->resolveAccessScope($request);
            
            $reports = $this->reportsService->listReports($scope);

            return response()->json([
                'success' => true,
                'data' => $reports
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * POST /api/finance/reports/{reportKey}/run
     */
 
    public function runReport (Request $request, string $reportKey) //: JsonResponse
    {
        
        try {
            $scope = $this->resolveAccessScope($request);
            $context = $this->createReportContext($request);

            $result = $this->reportsService->runReport($scope, $reportKey, $context); 

            return response()->json([
                'success' => true,
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * POST /api/finance/reports/{reportKey}/export/{format}
     */
    public function exportReport(Request $request, string $reportKey, string $format)
    {
        try {
            $scope = $this->resolveAccessScope($request);
            $context = $this->createReportContext($request);

            $exportHandle = $this->reportsService->exportReport($scope, $reportKey, $context, $format);

            return response()->streamDownload(
                function () use ($exportHandle) {
                    echo $exportHandle->content;
                },
                $exportHandle->filename,
                [
                    'Content-Type' => $exportHandle->contentType,
                ]
            );

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    private function resolveAccessScope(Request $request): AccessScope
    {
        $user = Auth::guard('tenant')->user();
        $tenantId = $request->header('X-Company-ID');
         
        $branchIds = [];  

        return new AccessScope(
            tenantId: $tenantId,
            allowedBranchIds: $branchIds,
            userId: $user->id
        );
    }

    private function createReportContext(Request $request): ReportContext
    {
        return new ReportContext(
            from: $request->get('from'),
            to: $request->get('to'),
            asOf: $request->get('as_of'),
            branchIds: $request->get('branch_ids', []),
            parameters: $request->except(['from', 'to', 'as_of', 'branch_ids'])
        );
    }
}