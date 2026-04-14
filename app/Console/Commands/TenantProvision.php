<?php

namespace App\Console\Commands;

 
 
use Illuminate\Support\Str;
use App\Models\Master\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Master\TenantDomain;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Artisan;

class TenantProvision extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */


    protected $signature = 'tenant:provision {name} {domain}';
    protected $description = 'Create a new tenant with its own database';

    // protected $signature = 'app:tenant-provision';

    /**
     * The console command description.
     *
     * @var string
     */
    // protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
{
    $service = app(\App\Services\TenantService::class);

    $tenant = $service->createTenant(
        $this->argument('name'),
        $this->argument('domain')
    );

    $this->info("Tenant {$tenant->name} created for domain {$tenant->domains->first()->domain}");
}

}
