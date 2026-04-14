<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use App\Models\Master\Tenant;

class MigrateTenants extends Command
{
    protected $signature = 'tenants:migrate {tenant?} {--F|force-refresh} {--seed}';

    protected $description = 'Run migrations for all tenants or a single tenant';

    public function handle()
    {
        $tenantSlug = $this->argument('tenant');

        if ($tenantSlug) {
            $tenant = Tenant::where('slug', $tenantSlug)->first();
            
            if (!$tenant) {
                $this->error("Tenant '{$tenantSlug}' not found.");
                return 1;
            }
            
            $this->migrateTenant($tenant);
        } else {
            $tenants = Tenant::all();
            
            if ($tenants->isEmpty()) {
                $this->error("No tenants found.");
                return 1;
            }
            
            $this->info("Migrating all {$tenants->count()} tenants...");
            $tenants->each(fn($tenant) => $this->migrateTenant($tenant));
        }

        return 0;
    }

    protected function migrateTenant(Tenant $tenant)
    {
        $this->info("Migrating tenant: {$tenant->name}");

        Config::set('database.connections.tenant.database', $tenant->database_name);
        DB::purge('tenant');
        DB::reconnect('tenant');

        $options = [
            '--database' => 'tenant',
            '--path' => 'database/migrations_tenant',
            '--force' => true,
        ];

        if ($this->option('force-refresh')) {
            $this->warn("⚠️  WARNING: This will DROP ALL TABLES and rebuild the database!");
            if ($this->confirm("Are you sure you want to refresh the database for tenant '{$tenant->name}'? This will destroy all data.")) {
                Artisan::call('migrate:fresh', [
                    '--database' => 'tenant',
                    '--path'     => 'database/migrations_tenant',
                    '--force'    => true,
                ]);
                $this->info("✓ Fresh migration completed for {$tenant->name}");
            } else {
                $this->info("✓ Skipped fresh migration for {$tenant->name}");
            }
        } else {
            // Regular migration - safe, only adds new tables
            Artisan::call('migrate', $options);
            $this->info("✓ Migration completed for {$tenant->name}");
        }

        if ($this->option('seed')) {
            Artisan::call('db:seed', [
                '--database' => 'tenant',
                '--force' => true,
            ]);
            $this->info("✓ Seeding completed for {$tenant->name}");
        }
    }
}