<?php
 

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Services\ChartOfAccountsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChartOfAccountsController extends Controller
{
    public function __construct(
        private ChartOfAccountsService $accountsService
    ) {}

    /**
     * GET /api/finance/accounts
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'parent_id' => 'nullable|uuid',
            'level' => 'nullable|integer|min:1',
        ]);

        $companyId = $request->header('X-Company-ID');
        $branchId = $request->header('X-Branch-ID');
        $parentId = $request->query('parent_id');
        $level = $request->query('level');

        try {
            $accounts = $this->accountsService->listAccounts(
                $companyId, 
                $branchId, 
                $parentId, 
                $level ? (int)$level : null
            );

            return response()->json([
                'success' => true,
                'data' => $accounts
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * GET /api/finance/accounts/{id}
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $companyId = $request->header('X-Company-ID');
        $branchId = $request->header('X-Branch-ID');

        try {
            $account = $this->accountsService->getAccountById($id, $companyId, $branchId);

            if (!$account) {
                return response()->json([
                    'success' => false,
                    'error' => 'Account not found'
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

    /**
     * POST /api/finance/accounts
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'parent_id' => 'nullable|uuid',
            'type' => 'required|string|max:255',
            'account_side' => 'required|in:debit,credit',
            'report_type' => 'required|in:balance_sheet,income_statement',
            'expenses_type' => 'nullable|in:not_expense,work_expense,normal_expense',
            'is_posting' => 'nullable|boolean',
            'metadata' => 'nullable|array',
        ]);

        $companyId = $request->header('X-Company-ID');
        $branchId = $request->header('X-Branch-ID');

        try {
            $account = $this->accountsService->createAccount(
                $request->all(),
                $companyId,
                $branchId
            );

            return response()->json([
                'success' => true,
                'data' => $account,
                'message' => 'Account created successfully'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * PUT /api/finance/accounts/{id}
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'code' => 'sometimes|string|max:255',
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'parent_id' => 'nullable|uuid',
            'type' => 'sometimes|string|max:255',
            'account_side' => 'sometimes|in:debit,credit',
            'report_type' => 'sometimes|in:balance_sheet,income_statement',
            'expenses_type' => 'nullable|in:not_expense,work_expense,normal_expense',
            'is_posting' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'metadata' => 'nullable|array',
        ]);

        $companyId = $request->header('X-Company-ID');
        $branchId = $request->header('X-Branch-ID');

        try {
            $account = $this->accountsService->updateAccount(
                $id,
                $request->all(),
                $companyId,
                $branchId
            );

            return response()->json([
                'success' => true,
                'data' => $account,
                'message' => 'Account updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * DELETE /api/finance/accounts/{id}
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $companyId = $request->header('X-Company-ID');
        $branchId = $request->header('X-Branch-ID');

        try {
            $this->accountsService->deleteAccount($id, $companyId, $branchId);

            return response()->json([
                'success' => true,
                'message' => 'Account deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }
}