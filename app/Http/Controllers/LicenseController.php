<?php

namespace App\Http\Controllers;

use App\Models\Master\License;
use App\Models\Master\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LicenseController extends Controller
{
    /**
     * Get all licenses with tenant information
     */
    public function index(Request $request): JsonResponse
    {
        $licenses = License::with('tenant')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'licenses' => $licenses
        ]);
    }

    /**
     * Create a new license
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'tenant_id' => 'required|uuid|exists:master.tenants,id',
            'plan_code' => 'required|string|max:50',
            'max_companies' => 'required|integer|min:1',
            'max_branches_per_company' => 'required|integer|min:1',
            'features_json' => 'nullable|array',
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after:starts_at',
            'status' => 'sometimes|in:active,inactive,expired,suspended',
        ]);

        DB::connection('master')->beginTransaction();
        try {
            // Check if tenant already has an active license
            $existingLicense = License::where('tenant_id', $request->tenant_id)
                ->where('status', 'active')
                ->first();

            if ($existingLicense) {
                throw ValidationException::withMessages([
                    'tenant_id' => 'Tenant already has an active license'
                ]);
            }

            $license = License::create([
                'tenant_id' => $request->tenant_id,
                'plan_code' => $request->plan_code,
                'max_companies' => $request->max_companies,
                'max_branches_per_company' => $request->max_branches_per_company,
                'features_json' => $request->features_json,
                'starts_at' => $request->starts_at,
                'ends_at' => $request->ends_at,
                'status' => $request->status ?? 'active',
            ]);

            DB::connection('master')->commit();

            return response()->json([
                'message' => 'License created successfully',
                'license' => $license->load('tenant')
            ], 201);

        } catch (ValidationException $e) {
            DB::connection('master')->rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::connection('master')->rollBack();
            return response()->json([
                'error' => 'Failed to create license: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get specific license
     */
    public function show(string $id): JsonResponse
    {
        $license = License::with('tenant')->find($id);

        if (!$license) {
            return response()->json([
                'error' => 'License not found'
            ], 404);
        }

        return response()->json([
            'license' => $license
        ]);
    }

    /**
     * Update license
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'plan_code' => 'sometimes|string|max:50',
            'max_companies' => 'sometimes|integer|min:1',
            'max_branches_per_company' => 'sometimes|integer|min:1',
            'features_json' => 'nullable|array',
            'starts_at' => 'sometimes|date',
            'ends_at' => 'sometimes|date|after:starts_at',
            'status' => 'sometimes|in:active,inactive,expired,suspended',
        ]);

        DB::connection('master')->beginTransaction();
        try {
            $license = License::find($id);

            if (!$license) {
                return response()->json([
                    'error' => 'License not found'
                ], 404);
            }

            $license->update($request->all());

            DB::connection('master')->commit();

            return response()->json([
                'message' => 'License updated successfully',
                'license' => $license->load('tenant')
            ]);

        } catch (\Exception $e) {
            DB::connection('master')->rollBack();
            return response()->json([
                'error' => 'Failed to update license: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete license
     */
    public function destroy(string $id): JsonResponse
    {
        DB::connection('master')->beginTransaction();
        try {
            $license = License::find($id);

            if (!$license) {
                return response()->json([
                    'error' => 'License not found'
                ], 404);
            }

            $license->delete();

            DB::connection('master')->commit();

            return response()->json([
                'message' => 'License deleted successfully'
            ]);

        } catch (\Exception $e) {
            DB::connection('master')->rollBack();
            return response()->json([
                'error' => 'Failed to delete license: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get licenses for specific tenant
     */
    public function getTenantLicenses(string $tenantId): JsonResponse
    {
        $licenses = License::with('tenant')
            ->where('tenant_id', $tenantId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'licenses' => $licenses
        ]);
    }
}