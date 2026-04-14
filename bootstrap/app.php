<?php

use Illuminate\Http\JsonResponse;
use App\Http\Middleware\EnsureIsAdmin;
use Illuminate\Foundation\Application;
use Illuminate\Auth\AuthenticationException;
use App\Http\Middleware\HandleApiUnauthenticated;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;

return Application::configure(basePath: dirname(__DIR__))
    
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',   
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )

     
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);
        
        $middleware->web(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);
        
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);
        
        $middleware->alias([
            'auth' => \Illuminate\Auth\Middleware\Authenticate::class,
            'admin.only' => \App\Http\Middleware\EnsureIsAdmin::class,
           'tenant.resolve' => \App\Http\Middleware\TenantResolverMiddleware::class,
           'tenant.jwt' => \App\Http\Middleware\TenantJwtMiddleware::class,
            'role' => \App\Http\Middleware\CheckRole::class,
            'cors' => \Illuminate\Http\Middleware\HandleCors::class,     
        ]);
      
    })
    
    ->withExceptions(function (Exceptions $exceptions): void {
        
    })
    
    ->create();
