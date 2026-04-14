<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Master\Tenant;
use App\Services\TenantService;
use Illuminate\Support\Facades\DB;
use App\Http\Responses\ApiResponse;
use App\Models\Master\TenantDomain;

class TenantController extends Controller
{
    public function store(Request $request, TenantService $service)
    {
        $request->validate([
            'name' => 'required|string',
            'domain' => 'required|string|unique:tenant_domains,domain',
        ]);

        $tenant = $service->createTenant($request->name, $request->domain);

        return response()->json([
            'message' => 'Tenant created successfully',
            'tenant' => $tenant
        ]);
    }

    public function attachDomain(Request $request)
    {
        $request->validate([
            'tenant_id' => 'required|uuid|exists:master.tenants,id',
            'domain' => 'required|string|unique:master.tenant_domains,domain',
        ]);

        DB::connection('master')->beginTransaction();
        try {
             
            $tenant = Tenant::find($request->tenant_id);
            if (!$tenant) {
                throw ValidationException::withMessages([
                    'tenant_id' => 'Tenant not found'
                ]);
            }

          
            $tenantDomain = TenantDomain::create([
                'tenant_id' => $tenant->id,
                'domain' => $request->domain,
            ]);

            DB::connection('master')->commit();

            return response()->json([
                'message' => 'Domain attached successfully',
                'tenant_domain' => $tenantDomain,
                'tenant' => $tenant
            ], 201);

        } catch (ValidationException $e) {
            DB::connection('master')->rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::connection('master')->rollBack();
            return response()->json([
                'error' => 'Failed to attach domain: ' . $e->getMessage()
            ], 500);
        }
    }
    public function detachDomain(Request $request)
    {
    $request->validate([
        'domain_id' => 'required|uuid|exists:master.tenant_domains,id',
    ]);

    DB::connection('master')->beginTransaction();
    try {
        $tenantDomain = TenantDomain::find($request->domain_id);
        
        // Prevent removing the last domain
        $domainCount = TenantDomain::where('tenant_id', $tenantDomain->tenant_id)->count();
        if ($domainCount <= 1) {
            throw ValidationException::withMessages([
                'domain_id' => 'Cannot remove the last domain from a tenant'
            ]);
        }

        $tenantDomain->delete();

        DB::connection('master')->commit();

        return response()->json([
            'message' => 'Domain detached successfully'
        ]);

        } catch (ValidationException $e) {
            DB::connection('master')->rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::connection('master')->rollBack();
            return response()->json([
                'error' => 'Failed to detach domain: ' . $e->getMessage()
            ], 500);
        }
    }

    public function listDomains(Request $request)
    {
        $request->validate([
            'tenant_id' => 'required|uuid|exists:master.tenants,id',
        ]);

        $domains = TenantDomain::where('tenant_id', $request->tenant_id)
            ->with('tenant')
            ->get();

        return response()->json([
            'domains' => $domains
        ]);
    }














    public function testConnection()
    {
        try {
            $tables = DB::select('SHOW TABLES');
            return ApiResponse::success([
                'database' => DB::getDatabaseName(),
                'tables_count' => count($tables),
                'status' => 'Connected successfully'
            ]);
        } catch (\Exception $e) {
            return ApiResponse::error('Database connection failed: ' . $e->getMessage());
        }
    }

    public function getTenantInfo(Request $request)
    {
        return ApiResponse::success([
            'tenant_domain' => $request->header('x-tenant-domain'),
            'current_database' => DB::getDatabaseName(),
            'timestamp' => now()->toISOString()
        ]);
    }
}
