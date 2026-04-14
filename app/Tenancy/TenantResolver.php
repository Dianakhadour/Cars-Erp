<?php

namespace App\Tenancy;

use Illuminate\Http\Request;
use App\Models\Master\Tenant;
use Illuminate\Support\Facades\DB;
use App\Models\Master\TenantDomain;
use Illuminate\Support\Facades\Config;

class TenantResolver
{
    public function resolve(Request $request)
    {
   
        $tenantDomain = $request->header('x-tenant-domain');
        
        if (!$tenantDomain) {
            return $this->errorResponse('Tenant domain header is missing', 400);
        }

       
      // Validate domain format and check if it's allowed by CORS
      if (!$this->isValidDomain($tenantDomain) || !$this->isAllowedDomain($tenantDomain)) {
        return $this->errorResponse('Invalid domain format or not allowed', 400);
    }


        
      //  $tenantdomain = TenantDomain::on('master')->where('domain', $tenantDomain)->first();
        $tenantDomain = TenantDomain::on('master')
        ->where('domain', $tenantDomain)
        ->with('tenant')
        ->first();  
        if (!$tenantDomain->tenant) {
            return $this->errorResponse('Tenant not found', 404);
        }

          
        $this->switchToTenantDatabase($tenantDomain->tenant);
        
        return $this->successResponse('Tenant resolved successfully', [
            'tenant' => $tenantDomain->tenant->only(['id', 'domain', 'name'])
        ]);
    }

    private function switchToTenantDatabase(Tenant $tenant)
    {
          
        Config::set('database.connections.tenant', [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => $tenant->database_name,
            'username' =>  env('DB_USERNAME'),
            'password' =>  env('DB_PASSWORD'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
        ]);

        // Set default connection to tenant
        Config::set('database.default', 'tenant');
        DB::purge('tenant');
        DB::reconnect('tenant');
    }

    private function isValidDomain($domain)
    {
        return preg_match('/^[a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z]{2,})+$/', $domain);
    }

    private function successResponse($message, $data = [])
    {
        return [
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => now()->toISOString()
        ];
    }

    private function errorResponse($message, $statusCode)
    {
        return [
            'success' => false,
            'message' => $message,
            'error_code' => $statusCode,
            'timestamp' => now()->toISOString()
        ];
    }
    private function isAllowedDomain($domain)
    {
        $allowedPatterns = [
            '/^.*\.carerp\.io$/',
            '/^.*\.e-carstech\.com$/',
            '/^carerp\.io$/',
            '/^e-carstech\.com$/',
             
        ]; 

        foreach ($allowedPatterns as $pattern) {
            if (preg_match($pattern, $domain)) {
                return true;
            }
        }

        return false;
    }
}