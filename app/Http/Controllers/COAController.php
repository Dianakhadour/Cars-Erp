<?php

namespace App\Http\Controllers;

use App\Models\Tenant\COAAccount;
use App\Models\Tenant\Company;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class COAController extends Controller
{
    /**
     * Get COA accounts for a company
     */
    public function index(string $companyId): JsonResponse
    {
        $company = Company::find($companyId);

        if (!$company) {
            return response()->json([
                'error' => 'Company not found'
            ], 404);
        }

        $accounts = COAAccount::with(['parent', 'children'])
            ->where('company_id', $companyId)
            ->orderBy('code')
            ->get();

        return response()->json([
            'accounts' => $accounts
        ]);
    }

    /**
     * Add COA account to company
     */
    public function store(Request $request, string $companyId): JsonResponse
    {
        $request->validate([
            'code' => 'required|string|max:20',
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|uuid|exists:coa_accounts,id',
            'type' => 'required|string|in:asset,liability,equity,income,expense',
            'is_posting' => 'boolean',
        ]);

        $company = Company::find($companyId);

        if (!$company) {
            return response()->json([
                'error' => 'Company not found'
            ], 404);
        }

        // Check if code already exists in this company
        $existingAccount = COAAccount::where('company_id', $companyId)
            ->where('code', $request->code)
            ->first();

        if ($existingAccount) {
            throw ValidationException::withMessages([
                'code' => 'Account code already exists in this company'
            ]);
        }

        $account = COAAccount::create([
            'company_id' => $companyId,
            'code' => $request->code,
            'name' => $request->name,
            'parent_id' => $request->parent_id,
            'type' => $request->type,
            'is_posting' => $request->is_posting ?? false,
        ]);

        return response()->json([
            'message' => 'COA account created successfully',
            'account' => $account->load(['parent', 'children'])
        ], 201);
    }

    /**
     * Update COA account
     */
    public function update(Request $request, string $companyId, string $accountId): JsonResponse
    {
        $request->validate([
            'code' => 'sometimes|string|max:20',
            'name' => 'sometimes|string|max:255',
            'type' => 'sometimes|string|in:asset,liability,equity,income,expense',
            'is_posting' => 'boolean',
        ]);

        $account = COAAccount::where('company_id', $companyId)
            ->where('id', $accountId)
            ->first();

        if (!$account) {
            return response()->json([
                'error' => 'COA account not found'
            ], 404);
        }

        // Check if new code conflicts with existing accounts
        if ($request->has('code') && $request->code !== $account->code) {
            $existingAccount = COAAccount::where('company_id', $companyId)
                ->where('code', $request->code)
                ->where('id', '!=', $accountId)
                ->first();

            if ($existingAccount) {
                throw ValidationException::withMessages([
                    'code' => 'Account code already exists in this company'
                ]);
            }
        }

        $account->update($request->all());

        return response()->json([
            'message' => 'COA account updated successfully',
            'account' => $account->load(['parent', 'children'])
        ]);
    }

    /**
     * Delete COA account
     */
    public function destroy(string $companyId, string $accountId): JsonResponse
    {
        $account = COAAccount::where('company_id', $companyId)
            ->where('id', $accountId)
            ->first();

        if (!$account) {
            return response()->json([
                'error' => 'COA account not found'
            ], 404);
        }

        // Check if account has children
        if ($account->children()->count() > 0) {
            return response()->json([
                'error' => 'Cannot delete account with child accounts'
            ], 422);
        }

        $account->delete();

        return response()->json([
            'message' => 'COA account deleted successfully'
        ]);
    }

    /**
     * Import default COA template for a company
     */
    public function importDefault(string $companyId): JsonResponse
    {
        $company = Company::find($companyId);

        if (!$company) {
            return response()->json([
                'error' => 'Company not found'
            ], 404);
        }

        DB::beginTransaction();
        try {
            $this->createDefaultCOA($company);
            
            DB::commit();

            return response()->json([
                'message' => 'Default COA imported successfully',
                'accounts' => COAAccount::where('company_id', $companyId)->get()
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Failed to import default COA: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create default Chart of Accounts
     */
    private function createDefaultCOA(Company $company): void
    {
        $defaultAccounts = [
            // Assets
            ['code' => '1000', 'name' => 'Current Assets', 'type' => 'asset', 'is_posting' => false],
            ['code' => '1100', 'name' => 'Cash and Bank', 'type' => 'asset', 'is_posting' => false],
            ['code' => '1110', 'name' => 'Cash on Hand', 'type' => 'asset', 'is_posting' => true],
            ['code' => '1120', 'name' => 'Bank Accounts', 'type' => 'asset', 'is_posting' => true],
            
            // Liabilities
            ['code' => '2000', 'name' => 'Current Liabilities', 'type' => 'liability', 'is_posting' => false],
            ['code' => '2100', 'name' => 'Accounts Payable', 'type' => 'liability', 'is_posting' => true],
            
            // Equity
            ['code' => '3000', 'name' => 'Equity', 'type' => 'equity', 'is_posting' => false],
            ['code' => '3100', 'name' => 'Owner\'s Capital', 'type' => 'equity', 'is_posting' => true],
            ['code' => '3200', 'name' => 'Retained Earnings', 'type' => 'equity', 'is_posting' => true],
            
            // Income
            ['code' => '4000', 'name' => 'Revenue', 'type' => 'income', 'is_posting' => false],
            ['code' => '4100', 'name' => 'Sales Revenue', 'type' => 'income', 'is_posting' => true],
            
            // Expenses
            ['code' => '5000', 'name' => 'Operating Expenses', 'type' => 'expense', 'is_posting' => false],
            ['code' => '5100', 'name' => 'Salaries and Wages', 'type' => 'expense', 'is_posting' => true],
            ['code' => '5200', 'name' => 'Rent Expense', 'type' => 'expense', 'is_posting' => true],
        ];

        $parentMap = [];
        foreach ($defaultAccounts as $accountData) {
            $parentCode = substr($accountData['code'], 0, -1) . '0';
            $parentId = $parentMap[$parentCode] ?? null;

            $account = COAAccount::create([
                'company_id' => $company->id,
                'code' => $accountData['code'],
                'name' => $accountData['name'],
                'parent_id' => $parentId,
                'type' => $accountData['type'],
                'is_posting' => $accountData['is_posting'],
            ]);

            $parentMap[$accountData['code']] = $account->id;
        }
    }
}