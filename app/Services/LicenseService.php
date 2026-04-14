<?php

namespace App\Services;

use App\Models\Master\License;
use App\Models\Master\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Models\Tenant\Company;
class LicenseService
{
    /**
     * Get current tenant's active license
     */
    public function getCurrentTenantLicense(): ?License
    {
        $tenantId = $this->getCurrentTenantId();
        
        if (!$tenantId) {
            return null;
        }

        // Cache for 5 minutes to avoid frequent master DB queries
        return Cache::remember("tenant_{$tenantId}_license", 300, function () use ($tenantId) {
            return License::on('master')
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->where('starts_at', '<=', now())
                ->where('ends_at', '>=', now())
                ->first();
        });
    }

    /**
     * Get current tenant ID from the request context
     */
    private function getCurrentTenantId(): ?string
    { 
         
        $host = request()->getHost();
        $host = explode(':', $host)[0];

        return Cache::remember("tenant_id_for_domain_{$host}", 3600, function () use ($host) {
            $tenantDomain = \App\Models\Master\TenantDomain::on('master')
                ->where('domain', $host)
                ->with('tenant')
                ->first();

            return $tenantDomain->tenant->id ?? null;
        });
    }

    /**
     * Check if tenant can create more companies
     */
    public function canCreateCompany(): bool
    {
        $license = $this->getCurrentTenantLicense();
        
        if (!$license) {
            return false; // No active license
        }

        $currentCompanyCount = $this->getCurrentCompanyCount();
        
        return $currentCompanyCount < $license->max_companies;
    }

    /**
     * Get current company count for tenant
     */
    public function getCurrentCompanyCount(): int
    {
        // This runs in tenant DB context
        return Company::count();
    }

    /**
     * Get license usage information
     */
    public function getLicenseUsage(): array
    {
        $license = $this->getCurrentTenantLicense();
        
        if (!$license) {
            return [
                'has_active_license' => false,
                'message' => 'No active license found'
            ];
        }

        $currentCount = $this->getCurrentCompanyCount();

        return [
            'has_active_license' => true,
            'license' => [
                'plan_code' => $license->plan_code,
                'max_companies' => $license->max_companies,
                'max_branches_per_company' => $license->max_branches_per_company,
                'ends_at' => $license->ends_at,
            ],
            'usage' => [
                'current_companies' => $currentCount,
                'remaining_companies' => max(0, $license->max_companies - $currentCount),
                'usage_percentage' => $license->max_companies > 0 ? 
                    round(($currentCount / $license->max_companies) * 100, 2) : 0,
            ],
            'can_create_more' => $currentCount < $license->max_companies,
        ];
    }

    /**
     * Clear license cache (call when license changes)
     */
    public function clearLicenseCache(?string $tenantId = null): void
    {
        $tenantId = $tenantId ?: $this->getCurrentTenantId();
        
        if ($tenantId) {
            Cache::forget("tenant_{$tenantId}_license");
        }
    }
}