<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HandleCors
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $allowedOrigins = [
            'https://carerp.io',
            'https://www.carerp.io',
            'https://e-carstech.com',
            'https://www.e-carstech.com',
            'http://localhost:3000',
            'http://localhost:8000',
            'http://127.0.0.1:3000',
            'http://127.0.0.1:8000'
        ];

        $origin = $request->headers->get('Origin');

    
        if ($request->getMethod() === 'OPTIONS') {
            $response = response('', 200);
        } else {
      
            if ($origin && !in_array($origin, $allowedOrigins)) {
                return response('Origin not allowed', 403);
            }
            $response = $next($request);
        }

       
        if ($origin && in_array($origin, $allowedOrigins)) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
        }
        
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, X-CSRF-TOKEN, Accept, Origin, X-API-KEY, X-Tenant-Domain');
        $response->headers->set('Access-Control-Allow-Credentials', 'true');
        $response->headers->set('Access-Control-Max-Age', '86400');

        return $response;
    }
    
    
}