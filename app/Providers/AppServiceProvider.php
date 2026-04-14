<?php

namespace App\Providers;

use Laravel\Sanctum\Sanctum;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
        
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Sanctum::usePersonalAccessTokenModel(function() {
        //     $defaultConnection = config('database.default');
            
        //     // Check if we're in a tenant context
        //     $host = request()->getHost();
        //     $host = explode(':', $host)[0];
            
        //     // Check if this is a master domain
        //     $masterDomains = explode(',', env('MASTER_DOMAINS', '127.0.0.1,localhost'));
            
        //     if (in_array($host, $masterDomains)) {
        //         return \App\Models\Master\MasterPersonalAccessToken::class;
        //     }
            
        //     return \App\Models\PersonalAccessToken::class;
        // });
    }
}
