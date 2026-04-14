<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Tenancy\TenantResolver;
use App\Http\Responses\ApiResponse;
use Symfony\Component\HttpFoundation\Response;

class TenantResolverMiddleware
{
    protected $tenantResolver;

    public function __construct(TenantResolver $tenantResolver)
    {
        $this->tenantResolver = $tenantResolver;
    }

    public function handle(Request $request, Closure $next): Response
    {
     
        if ($this->shouldSkipTenantResolution($request)) {
            return $next($request);
        }

        
        $result = $this->tenantResolver->resolve($request);

        if (!$result['success']) {
            return ApiResponse::error($result['message'], $result['error_code']);
        }

       
        $response = $next($request);

      
        $response->headers->set('X-Tenant-ID', $result['data']['tenant']['id'] ?? '');
        $response->headers->set('X-Tenant-Domain', $result['data']['tenant']['domain'] ?? '');

        return $response;
    }

    private function shouldSkipTenantResolution(Request $request): bool
    {
        $skipRoutes = [
            'sanctum/*',
            'auth/*',
            'login',
            'logout',
        ];

        foreach ($skipRoutes as $route) {
            if ($request->is($route)) {
                return true;
            }
        }

        return false;
    }
}