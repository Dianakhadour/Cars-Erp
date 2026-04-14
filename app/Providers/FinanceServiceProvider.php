<?php
 
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Contracts\Services\Finance\ReportsService;
use App\Services\Finance\ReportsService as DefaultReportsService;
use App\Services\Finance\ReportRegistry;
use App\Services\Finance\ReportRunner;
use App\Services\Finance\PermissionGuard;

class FinanceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ReportRegistry::class);
        $this->app->singleton(ReportRunner::class);
        $this->app->singleton(PermissionGuard::class);
        
        $this->app->bind(ReportsService::class, DefaultReportsService::class);
    }

    public function boot(): void
    {
        //
    }
}