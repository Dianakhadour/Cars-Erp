<?php
 

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Services\ChartOfAccountIntegrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChartOfAccountIntegrationsController extends Controller
{
    public function __construct(
        private ChartOfAccountIntegrationService $integrationService
    ) {}

    /**
     * GET /api/finance/integrations
     */
    public function index(Request $request): JsonResponse
    {
        $companyId = $request->header('X-Company-ID');
        $branchId = $request->header('X-Branch-ID');

        try {
            $integrations = $this->integrationService->getIntegrations($companyId, $branchId);

            return response()->json([
                'success' => true,
                'data' => $integrations
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * GET /api/finance/integrations/types
     */
    public function getIntegrationTypes(): JsonResponse
    {
        try {
            $types = $this->integrationService->getIntegrationTypes();

            return response()->json([
                'success' => true,
                'data' => $types
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * GET /api/finance/integrations/{id}
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $companyId = $request->header('X-Company-ID');
        $branchId = $request->header('X-Branch-ID');

        try {
            $integration = $this->integrationService->getIntegrationById($id, $companyId, $branchId);

            if (!$integration) {
                return response()->json([
                    'success' => false,
                    'error' => 'Integration not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $integration
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * POST /api/finance/integrations
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'account_id' => 'required|uuid',
            'integration_type' => 'required|string|max:255',
            'document_type' => 'nullable|string|max:255',
            'priority' => 'nullable|integer|min:0',
            'metadata' => 'nullable|array',
        ]);

        $companyId = $request->header('X-Company-ID');
        $branchId = $request->header('X-Branch-ID');

        try {
            $integration = $this->integrationService->createIntegration(
                $request->all(),
                $companyId,
                $branchId
            );

            return response()->json([
                'success' => true,
                'data' => $integration,
                'message' => 'Integration created successfully'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * PUT /api/finance/integrations/{id}
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'account_id' => 'sometimes|uuid',
            'integration_type' => 'sometimes|string|max:255',
            'document_type' => 'nullable|string|max:255',
            'priority' => 'nullable|integer|min:0',
            'metadata' => 'nullable|array',
        ]);

        $companyId = $request->header('X-Company-ID');
        $branchId = $request->header('X-Branch-ID');

        try {
            $integration = $this->integrationService->updateIntegration(
                $id,
                $request->all(),
                $companyId,
                $branchId
            );

            return response()->json([
                'success' => true,
                'data' => $integration,
                'message' => 'Integration updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * DELETE /api/finance/integrations/{id}
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $companyId = $request->header('X-Company-ID');
        $branchId = $request->header('X-Branch-ID');

        try {
            $this->integrationService->deleteIntegration($id, $companyId, $branchId);

            return response()->json([
                'success' => true,
                'message' => 'Integration deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * POST /api/finance/integrations/resolve-account
     *  
     */
    public function resolveAccount(Request $request): JsonResponse
    {
        $request->validate([
            'integration_type' => 'required|string|max:255',
            'document_type' => 'nullable|string|max:255',
        ]);

        $branchId = $request->header('X-Branch-ID');

        try {
            $account = $this->integrationService->resolveAccountForIntegration(
                $request->integration_type,
                $request->document_type,
                $branchId
            );

            if (!$account) {
                return response()->json([
                    'success' => false,
                    'error' => 'No account mapping found for the specified criteria'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $account
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }
}