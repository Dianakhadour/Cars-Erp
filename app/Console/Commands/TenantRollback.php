<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class TenantRollback extends Command
{
    protected $signature = 'tenant:rollback {tenant?} {--step=1}';
    protected $description = 'Rollback migrations for tenant(s)';

    public function handle()
    {
        $tenantSlug = $this->argument('tenant');

        if ($tenantSlug) {
            $tenant = Tenant::where('slug', $tenantSlug)->first();
            
            if (!$tenant) {
                $this->error("Tenant '{$tenantSlug}' not found.");
                return 1;
            }
            
            $this->rollbackTenant($tenant);
        } else {
            $tenants = Tenant::all();
            
            if ($tenants->isEmpty()) {
                $this->error("No tenants found.");
                return 1;
            }
            
            $this->info("Rolling back all {$tenants->count()} tenants...");
            $tenants->each(fn($tenant) => $this->rollbackTenant($tenant));
        }
    }

    protected function rollbackTenant(Tenant $tenant)
    {
        $this->info("Rolling back tenant: {$tenant->name}");

        Config::set('database.connections.tenant.database', $tenant->database_name);
        DB::purge('tenant');
        DB::reconnect('tenant');

        Artisan::call('migrate:rollback', [
            '--database' => 'tenant',
            '--path' => 'database/migrations_tenant',
            '--step' => $this->option('step'),
            '--force' => true,
        ]);

        $this->info("Rollback completed for {$tenant->name}");
    }
}