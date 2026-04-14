<?php

namespace App\Services;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Artisan;
use App\Models\Master\Tenant;
use App\Models\Master\TenantDomain;

class TenantService
{
    public function createTenant(string $name, string $domain): Tenant
    {
        $slug = Str::slug($name, '_');
        $dbName = "tenant_{$slug}";

        
        DB::statement("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

      
        $tenant = Tenant::create([
            'name' => $name,
            'slug' => $slug,
            'database_name' => $dbName,
        ]);

 
        TenantDomain::create([
            'tenant_id' => $tenant->id,
            'domain' => $domain,
        ]);

        
        Config::set('database.connections.tenant.database', $dbName);
        DB::purge('tenant');
        DB::reconnect('tenant');

        Artisan::call('migrate', [
            '--path' => 'database/migrations_tenant',
            '--database' => 'tenant',
            '--force' => true,
        ]);

        return $tenant;
    }
    protected function setupTenantDatabase(string $databaseName)
    {
        // Set the database for tenant connection
        Config::set('database.connections.tenant.database', $databaseName);
        DB::purge('tenant');
        DB::reconnect('tenant');

        // Run migrations
        Artisan::call('migrate', [
            '--path' => 'database/migrations_tenant',
            '--database' => 'tenant',
            '--force' => true,
        ]);

        // Create initial admin user for the tenant
        $this->createInitialTenantUser();
    }
    
    // protected function createInitialTenantUser()
    // {
    //     $userClass = config('auth.providers.users.model', \App\Models\User::class);
        
    //     $user = new $userClass();
    //     $user->id = (string) Str::uuid();
    //     $user->name = 'Tenant Admin';
    //     $user->email = 'admin@tenant.com';
    //     $user->password = Hash::make('password');
    //     $user->email_verified_at = now();
    //     $user->save();

    //     return $user;
    // }

   
    
    protected function createInitialTenantAdmin()
    {
        $user = User::create([
            'name' => 'Tenant Admin',
            'email' => 'admin@tenant.com',
            'password' => Hash::make('password'),
            'active' => true,
        ]);
    
        // Assign super admin role
        $superAdminRole = \App\Models\Role::where('slug', 'super-admin')->first();
        if ($superAdminRole) {
            $user->roles()->attach($superAdminRole->id);
        }
    
        return $user;
    }
    public function getTenantDomains($tenantId)
    {
        return TenantDomain::where('tenant_id', $tenantId)->get();
    }
}
