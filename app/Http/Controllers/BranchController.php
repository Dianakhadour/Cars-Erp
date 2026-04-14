<?php

namespace App\Http\Controllers;

 
use Illuminate\Http\Request;
use App\Models\Tenant\Branch;
use App\Models\Tenant\Company;
use App\Services\LicenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class BranchController extends Controller
{
    protected LicenseService $licenseService;

    public function __construct(LicenseService $licenseService)
    {
        $this->licenseService = $licenseService;
    }
    public function index(string $companyId): JsonResponse
    {
        $company = Company::find($companyId);

        if (!$company) {
            return response()->json([
                'error' => 'Company not found'
            ], 404);
        }

        $branches = Branch::where('company_id', $companyId)->get();

        return response()->json([
            'branches' => $branches
        ]);
    }
    public function show(string $companyId, string $branchId): JsonResponse
    {
        $branch = Branch::where('company_id', $companyId)
            ->where('id', $branchId)
            ->with('company')
            ->first();

        if (!$branch) {
            return response()->json([
                'error' => 'Branch not found'
            ], 404);
        }

        return response()->json([
            'branch' => $branch
        ]);
    }
    /**
     * Create a new branch
     */
    public function store(Request $request, string $companyId): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:10',
            'city' => 'required|string|max:100',
            'is_main' => 'boolean',
        ]);

        $company = Company::find($companyId);

        if (!$company) {
            return response()->json([
                'error' => 'Company not found'
            ], 404);
        }

        // Check branch limit for this company
        if (!$this->canCreateBranch($companyId)) {
            $usage = $this->getBranchLicenseUsage($companyId);
            
            throw ValidationException::withMessages([
                'name' => 'Branch limit reached for this company. ' . 
                         "You have {$usage['current_branches']} out of {$usage['max_branches']} branches."
            ]);
        }

        $branch = Branch::create([
            'company_id' => $companyId,
            'name' => $request->name,
            'code' => $request->code,
            'city' => $request->city,
            'is_main' => $request->is_main ?? false,
        ]);

        return response()->json([
            'message' => 'Branch created successfully',
            'branch' => $branch,
            'license_usage' => $this->getBranchLicenseUsage($companyId)
        ], 201);
    }

    /**
     * Check if company can create more branches
     */
    private function canCreateBranch(string $companyId): bool
    {
        $license = $this->licenseService->getCurrentTenantLicense();
        
        if (!$license) {
            return false;
        }

        $currentBranchCount = Branch::where('company_id', $companyId)->count();
        
        return $currentBranchCount < $license->max_branches_per_company;
    }

    /**
     * Get branch license usage for a company
     */
    private function getBranchLicenseUsage(string $companyId): array
    {
        $license = $this->licenseService->getCurrentTenantLicense();
        $currentBranchCount = Branch::where('company_id', $companyId)->count();

        return [
            'current_branches' => $currentBranchCount,
            'max_branches' => $license ? $license->max_branches_per_company : 0,
            'remaining_branches' => $license ? max(0, $license->max_branches_per_company - $currentBranchCount) : 0,
            'can_create_more' => $license && $currentBranchCount < $license->max_branches_per_company,
        ];
    }

    public function update(Request $request, string $companyId, string $branchId): JsonResponse
{
    $request->validate([
        'name' => 'sometimes|string|max:255',
        'code' => 'sometimes|string|max:10',
        'city' => 'sometimes|string|max:100',
        'is_main' => 'sometimes|boolean',
    ]);

    $company = Company::find($companyId);
    if (!$company) {
        return response()->json([
            'error' => 'Company not found'
        ], 404);
    }

    $branch = Branch::where('company_id', $companyId)
                    ->where('id', $branchId)
                    ->first();

    if (!$branch) {
        return response()->json([
            'error' => 'Branch not found for this company'
        ], 404);
    }

     
    if ($request->has('is_main') && $request->is_main === true) {
        Branch::where('company_id', $companyId)
              ->where('id', '!=', $branchId)
              ->update(['is_main' => false]);
    }

    $branch->update([
        'name' => $request->name ?? $branch->name,
        'code' => $request->code ?? $branch->code,
        'city' => $request->city ?? $branch->city,
        'is_main' => $request->has('is_main') ? $request->is_main : $branch->is_main,
    ]);

    return response()->json([
        'message' => 'Branch updated successfully',
        'branch' => $branch->fresh(),
        'license_usage' => $this->getBranchLicenseUsage($companyId)
    ]);
}
public function destroy(string $companyId, string $branchId): JsonResponse
{
    $company = Company::find($companyId);
    if (!$company) {
        return response()->json([
            'error' => 'Company not found'
        ], 404);
    }

    $branch = Branch::where('company_id', $companyId)
                    ->where('id', $branchId)
                    ->first();

    if (!$branch) {
        return response()->json([
            'error' => 'Branch not found for this company'
        ], 404);
    }

    // Check if this is the main branch  
    if ($branch->is_main) {
        return response()->json([
            'error' => 'Cannot delete the main branch'
        ], 422);
    }

   
    $branch->delete();

    return response()->json([
        'message' => 'Branch deleted successfully',
        'license_usage' => $this->getBranchLicenseUsage($companyId)
    ]);
}
}