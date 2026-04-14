<?php

namespace App\Repositories;

use App\Models\Tenant\ChartOfAccountIntegration;
use App\Enums\IntegrationType;
use Illuminate\Database\Eloquent\Collection;

class ChartOfAccountIntegrationRepository
{
    public function findAll(string $companyId, ?string $branchId = null): Collection
    {
        $query = ChartOfAccountIntegration::with(['account', 'branch'])
            ->where('company_id', $companyId)
            ->orderedByPriority();

        if ($branchId) {
            $query->where('branch_id', $branchId);
        } else {
            $query->whereNull('branch_id');
        }

        return $query->get();
    }

    public function findById(string $id, string $companyId, ?string $branchId = null): ?ChartOfAccountIntegration
    {
        $query = ChartOfAccountIntegration::with(['account', 'branch'])
            ->where('company_id', $companyId);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        } else {
            $query->whereNull('branch_id');
        }

        return $query->find($id);
    }

    public function resolveAccount(
        string $integrationType, 
        ?string $documentType = null, 
        ?string $branchId = null
    ): ?ChartOfAccountIntegration {
        $query = ChartOfAccountIntegration::with('account')
            ->activeAccount()
            ->byIntegrationType($integrationType)
            ->orderedByPriority();

        if ($documentType) {
            $query->byDocumentType($documentType);
        }

        if ($branchId) {
            // First try branch-specific integration
            $branchIntegration = (clone $query)
                ->byBranch($branchId)
                ->first();

            if ($branchIntegration) {
                return $branchIntegration;
            }
        }

        // Fall back to global (branch_id = null) integration
        return $query->byBranch(null)->first();
    }

    public function integrationExists(
        string $integrationType,
        string $accountId,
        ?string $documentType = null,
        ?string $branchId = null
    ): bool {
        $query = ChartOfAccountIntegration::where('integration_type', $integrationType)
            ->where('account_id', $accountId);

        if ($documentType) {
            $query->where('document_type', $documentType);
        } else {
            $query->whereNull('document_type');
        }

        if ($branchId) {
            $query->where('branch_id', $branchId);
        } else {
            $query->whereNull('branch_id');
        }

        return $query->exists();
    }

    public function save(ChartOfAccountIntegration $integration): void
    {
        $integration->save();
    }

    public function delete(ChartOfAccountIntegration $integration): bool
    {
        return $integration->delete();
    }
}