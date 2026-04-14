<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use App\Tenancy\TenantResolver;

class TenantJwtMiddleware
{
    protected $tenantResolver;

    public function __construct(TenantResolver $tenantResolver)
    {
        $this->tenantResolver = $tenantResolver;
    }

    public function handle(Request $request, Closure $next): Response
    {
        // First resolve tenant and set database connection
        $result = $this->tenantResolver->resolve($request);

        if (!$result['success']) {
            return response()->json([
                'error' => $result['message']
            ], 400);
        }

        // Now try JWT authentication with tenant connection set
        try {
            JWTAuth::parseToken()->authenticate();
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Token is invalid or expired'
            ], 401);
        }

        return $next($request);
    }
}