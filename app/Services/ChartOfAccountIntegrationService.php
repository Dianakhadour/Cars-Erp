<?php
 

namespace App\Services;

use App\Models\Tenant\ChartOfAccountIntegration;
use App\Models\Tenant\COAAccount;
use App\Repositories\ChartOfAccountIntegrationRepository;
use App\Enums\IntegrationType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChartOfAccountIntegrationService
{
    public function __construct(
        private ChartOfAccountIntegrationRepository $repository
    ) {}

    public function getIntegrations(string $companyId, ?string $branchId = null): array
    {
        $integrations = $this->repository->findAll($companyId, $branchId);
        
        return $integrations->map(function (ChartOfAccountIntegration $integration) {
            return $this->formatIntegrationResponse($integration);
        })->toArray();
    }

    public function getIntegrationById(string $id, string $companyId, ?string $branchId = null): ?array
    {
        $integration = $this->repository->findById($id, $companyId, $branchId);
        
        if (!$integration) {
            return null;
        }
        
        return $this->formatIntegrationResponse($integration);
    }

    public function createIntegration(array $data, string $companyId, ?string $branchId = null): array
    {
        // Validate account exists and is active
        $account = COAAccount::where('company_id', $companyId)
            ->where('id', $data['account_id'])
            ->active()
            ->first();

        if (!$account) {
            throw ValidationException::withMessages([
                'account_id' => 'Account not found or not active.'
            ]);
        }

        // Validate integration type
        if (!IntegrationType::tryFrom($data['integration_type'])) {
            throw ValidationException::withMessages([
                'integration_type' => 'Invalid integration type.'
            ]);
        }

        // Check for duplicate integration
        if ($this->repository->integrationExists(
            $data['integration_type'],
            $data['account_id'],
            $data['document_type'] ?? null,
            $branchId
        )) {
            throw ValidationException::withMessages([
                'integration_type' => 'Integration mapping already exists for this account and type.'
            ]);
        }

        return DB::transaction(function () use ($data, $companyId, $branchId) {
            $integration = ChartOfAccountIntegration::create([
                'company_id' => $companyId,
                'account_id' => $data['account_id'],
                'branch_id' => $branchId,
                'integration_type' => $data['integration_type'],
                'document_type' => $data['document_type'] ?? null,
                'priority' => $data['priority'] ?? 0,
                'metadata' => $data['metadata'] ?? null,
            ]);

            return $this->formatIntegrationResponse($integration);
        });
    }

    public function updateIntegration(string $id, array $data, string $companyId, ?string $branchId = null): array
    {
        $integration = $this->repository->findById($id, $companyId, $branchId);
        
        if (!$integration) {
            throw ValidationException::withMessages([
                'integration' => 'Integration not found.'
            ]);
        }

        // Validate account if being updated
        if (isset($data['account_id'])) {
            $account = COAAccount::where('company_id', $companyId)
                ->where('id', $data['account_id'])
                ->active()
                ->first();

            if (!$account) {
                throw ValidationException::withMessages([
                    'account_id' => 'Account not found or not active.'
                ]);
            }
        }

        return DB::transaction(function () use ($integration, $data) {
            $updateData = [];
            
            $allowedFields = [
                'account_id', 'integration_type', 'document_type', 'priority', 'metadata'
            ];
            
            foreach ($allowedFields as $field) {
                if (array_key_exists($field, $data)) {
                    $updateData[$field] = $data[$field];
                }
            }

            $integration->update($updateData);

            return $this->formatIntegrationResponse($integration->fresh(['account', 'branch']));
        });
    }

    public function deleteIntegration(string $id, string $companyId, ?string $branchId = null): void
    {
        $integration = $this->repository->findById($id, $companyId, $branchId);
        
        if (!$integration) {
            throw ValidationException::withMessages([
                'integration' => 'Integration not found.'
            ]);
        }

        $integration->delete();
    }

    public function resolveAccountForIntegration(
        string $integrationType,
        ?string $documentType = null,
        ?string $branchId = null
    ): ?array {
        $integration = $this->repository->resolveAccount($integrationType, $documentType, $branchId);
        
        if (!$integration) {
            return null;
        }

        return [
            'integration_id' => $integration->id,
            'account_id' => $integration->account_id,
            'account_code' => $integration->account->code,
            'account_name' => $integration->account->name,
            'account_side' => $integration->account->account_side,
            'priority' => $integration->priority,
        ];
    }

    public function getIntegrationTypes(): array
    {
        return IntegrationType::forSelect();
    }

    private function formatIntegrationResponse(ChartOfAccountIntegration $integration): array
    {
        return [
            'id' => $integration->id,
            'integration_type' => $integration->integration_type,
            'integration_type_label' => IntegrationType::tryFrom($integration->integration_type)?->label() ?? $integration->integration_type,
            'document_type' => $integration->document_type,
            'priority' => $integration->priority,
            'account' => $integration->account ? [
                'id' => $integration->account->id,
                'code' => $integration->account->code,
                'name' => $integration->account->name,
                'account_side' => $integration->account->account_side->value,
                'is_posting' => $integration->account->is_posting,
            ] : null,
            'branch' => $integration->branch ? [
                'id' => $integration->branch->id,
                'name' => $integration->branch->name,
                'code' => $integration->branch->code,
            ] : null,
            'metadata' => $integration->metadata,
            'created_at' => $integration->created_at,
            'updated_at' => $integration->updated_at,
        ];
    }
}