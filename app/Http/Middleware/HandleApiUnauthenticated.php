<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\PersonalAccessToken;
use Illuminate\Support\Facades\Log;
use Illuminate\Auth\AuthenticationException;

class HandleApiUnauthenticated
{
    public function handle(Request $request, Closure $next)
    {
        // try {
        //     return $next($request);
        // } catch (AuthenticationException $e) {
        //     if ($request->expectsJson() || $request->is('api/*')) {
        //         return new JsonResponse(['message' => 'Unauthenticated.'], 401);
        //     }

        //     throw $e;
        // }

        Log::info('=== SANCTUM AUTH DEBUG ===');
        Log::info('Bearer Token: ' . $request->bearerToken());
        Log::info('Has Tenant: ' . (!is_null($request->attributes->get('tenant')) ? 'YES' : 'NO'));
        Log::info('Tenant DB: ' . config('database.connections.tenant.database'));
        
        
        $token = $request->bearerToken();
        if ($token) {
            $tokenParts = explode('|', $token);
            $tokenId = $tokenParts[0] ?? null;
            
            if ($tokenId) { 
                $tokenModel = \App\Models\PersonalAccessToken::find($tokenId);
                Log::info('Token Found Manually: ' . ($tokenModel ? 'YES' : 'NO'));
                Log::info('Token User: ' . ($tokenModel?->tokenable?->email ?? 'NO USER'));
            }
        }
        
        Log::info('=======================');

        return $next($request);
    }
}
