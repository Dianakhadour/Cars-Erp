<?php

namespace App\Http\Controllers;

 
use Illuminate\Http\Request;
use App\Models\Tenant\Company;
use App\Services\LicenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Models\Tenant\NumberSequence;
use App\Models\Tenant\CompanyRegistration;
use Illuminate\Validation\ValidationException;

class CompanyController extends Controller
{

    protected LicenseService $licenseService;

    public function __construct(LicenseService $licenseService)
    {
        $this->licenseService = $licenseService;
    }
     /**
     * Get all companies for the current tenant
     */
    public function index(): JsonResponse
    {
        $companies = Company::with(['registrations', 'branches'])->get();

        return response()->json([
            'companies' => $companies
        ]);
    }

    /**
     * Create a new company
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'cr_number' => 'nullable|string|max:100',
            'cr_issue_date' => 'nullable|date',
            'registrations' => 'nullable|array',
            'registrations.*.type' => 'required|string|in:VAT,Tax,Activity,Other',
            'registrations.*.value' => 'required|string|max:100',
            'registrations.*.issue_date' => 'nullable|date',
        ]);

        // License check for max_companies
        if (!$this->licenseService->canCreateCompany()) {
            $usage = $this->licenseService->getLicenseUsage();
       
            if (!$usage['has_active_license']) {
                throw ValidationException::withMessages([
                    'name' => 'No active license found. Please contact support.'
                ]);
            }

            throw ValidationException::withMessages([
                'name' => 'Company limit reached for your license. ' . 
                         "You have {$usage['usage']['current_companies']} out of {$usage['license']['max_companies']} companies."
            ]);
        }
        DB::beginTransaction();
        try {
            $company = Company::create([
                'name' => $request->name,
                'cr_number' => $request->cr_number,
                'cr_issue_date' => $request->cr_issue_date,
            ]);

            // Create registrations if provided
            if ($request->has('registrations')) {
                foreach ($request->registrations as $registrationData) {
                    CompanyRegistration::create([
                        'company_id' => $company->id,
                        'type' => $registrationData['type'],
                        'value' => $registrationData['value'],
                        'issue_date' => $registrationData['issue_date'] ?? null,
                    ]);
                }
            }

            // Create default number sequences
            $this->createDefaultSequences($company);

            DB::commit();

            return response()->json([
                'message' => 'Company created successfully',
                'company' => $company->load(['registrations', 'numberSequences'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Failed to create company: ' . $e->getMessage()
            ], 500);
        }
    }
     /**
     * Get license usage information
     */
    public function getLicenseInfo(): JsonResponse
    {
        $usage = $this->licenseService->getLicenseUsage();

        return response()->json($usage);
    }
    /**
     * Get specific company
     */
    public function show(string $id): JsonResponse
    {
        $company = Company::with(['registrations', 'branches', 'coaAccounts', 'numberSequences'])
            ->find($id);

        if (!$company) {
            return response()->json([
                'error' => 'Company not found'
            ], 404);
        }

        return response()->json([
            'company' => $company
        ]);
    }

    /**
     * Update company
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'cr_number' => 'nullable|string|max:100',
            'cr_issue_date' => 'nullable|date',
        ]);

        $company = Company::find($id);

        if (!$company) {
            return response()->json([
                'error' => 'Company not found'
            ], 404);
        }

        $company->update($request->all());

        return response()->json([
            'message' => 'Company updated successfully',
            'company' => $company
        ]);
    }

    /**
     * Delete company
     */
    public function destroy(string $id): JsonResponse
    {
        $company = Company::find($id);

        if (!$company) {
            return response()->json([
                'error' => 'Company not found'
            ], 404);
        }

        // Check if company has branches
        if ($company->branches()->count() > 0) {
            return response()->json([
                'error' => 'Cannot delete company with existing branches'
            ], 422);
        }

        $company->delete();

        return response()->json([
            'message' => 'Company deleted successfully'
        ]);
    }

    /**
     * Add registration to company
     */
    public function addRegistration(Request $request, string $companyId): JsonResponse
    {
        $request->validate([
            'type' => 'required|string|in:VAT,Tax,Activity,Other',
            'value' => 'required|string|max:100',
            'issue_date' => 'nullable|date',
        ]);

        $company = Company::find($companyId);

        if (!$company) {
            return response()->json([
                'error' => 'Company not found'
            ], 404);
        }

        $registration = CompanyRegistration::create([
            'company_id' => $company->id,
            'type' => $request->type,
            'value' => $request->value,
            'issue_date' => $request->issue_date,
        ]);

        return response()->json([
            'message' => 'Registration added successfully',
            'registration' => $registration
        ], 201);
    }

    /**
     * Remove registration from company
     */
    public function removeRegistration(string $companyId, string $registrationId): JsonResponse
    {
        $registration = CompanyRegistration::where('company_id', $companyId)
            ->where('id', $registrationId)
            ->first();

        if (!$registration) {
            return response()->json([
                'error' => 'Registration not found'
            ], 404);
        }

        $registration->delete();

        return response()->json([
            'message' => 'Registration removed successfully'
        ]);
    }

    /**
     * Create default number sequences for a company
     */
    private function createDefaultSequences(Company $company): void
    {
        $defaultSequences = [
            ['doc_type' => 'JE', 'prefix' => 'JE-', 'next_number' => 1, 'padding' => 5, 'yearly_reset' => false],
            ['doc_type' => 'INV', 'prefix' => 'INV-', 'next_number' => 1, 'padding' => 6, 'yearly_reset' => true],
            ['doc_type' => 'PO', 'prefix' => 'PO-', 'next_number' => 1, 'padding' => 6, 'yearly_reset' => true],
            ['doc_type' => 'PYMT', 'prefix' => 'PYMT-', 'next_number' => 1, 'padding' => 6, 'yearly_reset' => true],
        ];

        foreach ($defaultSequences as $sequence) {
            NumberSequence::create(array_merge($sequence, ['company_id' => $company->id]));
        }
    }
    
}
