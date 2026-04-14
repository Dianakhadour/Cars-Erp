<?php
 

namespace App\Services;

use App\Models\Tenant\COAAccount;
use App\Repositories\COAAccountRepository;
use App\Enums\AccountSide;
use App\Enums\ReportType;
use App\Enums\ExpensesType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChartOfAccountsService
{
    public function __construct(
        private COAAccountRepository $repository
    ) {}

    public function listAccounts(string $companyId, ?string $branchId = null, ?string $parentId = null, ?int $level = null): array
    {
        $accounts = $this->repository->getAccountsTree($parentId, $level, $branchId);
        
        return $accounts->map(function (COAAccount $account) {
            return $this->formatAccountResponse($account);
        })->toArray();
    }

    public function getAccountById(string $id, string $companyId, ?string $branchId = null): ?array
    {
        $account = $this->repository->findById($id, $companyId, $branchId);
        
        if (!$account) {
            return null;
        }
        
        return $this->formatAccountResponse($account);
    }

    public function createAccount(array $data, string $companyId, ?string $branchId = null): array
    {
 
        if ($this->repository->accountNumberExists($data['code'], $companyId, $branchId)) {
            throw ValidationException::withMessages([
                'code' => 'Account number already exists for this company/branch.'
            ]);
        }

        return DB::transaction(function () use ($data, $companyId, $branchId) {
            $account = COAAccount::create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'code' => $data['code'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'parent_id' => $data['parent_id'] ?? null,
                'type' => $data['type'],
                'account_side' => AccountSide::from($data['account_side']),
                'report_type' => ReportType::from($data['report_type']),
                'expenses_type' => ExpensesType::from($data['expenses_type'] ?? 'not_expense'),
                'is_posting' => $data['is_posting'] ?? true,
                'is_active' => true,
                'metadata' => $data['metadata'] ?? null,
            ]);

            return $this->formatAccountResponse($account);
        });
    }

    public function updateAccount(string $id, array $data, string $companyId, ?string $branchId = null): array
    {
        $account = $this->repository->findById($id, $companyId, $branchId);
        
        if (!$account) {
            throw ValidationException::withMessages([
                'account' => 'Account not found.'
            ]);
        }
 
        if (isset($data['account_side']) && $data['account_side'] !== $account->account_side->value) {
            if ($this->repository->hasPostedEntries($id)) {
                throw ValidationException::withMessages([
                    'account_side' => 'Cannot change account side: account has posted entries.'
                ]);
            }
        }

   
        if (isset($data['parent_id']) && $data['parent_id'] !== $account->parent_id) {
            $this->validateParentChange($account, $data['parent_id']);
        }

        return DB::transaction(function () use ($account, $data) {
            $updateData = [];
            
            $allowedFields = [
                'name', 'description', 'parent_id', 'type', 'account_side', 
                'report_type', 'expenses_type', 'is_posting', 'is_active', 'metadata'
            ];
            
            foreach ($allowedFields as $field) {
                if (array_key_exists($field, $data)) {
                    if (in_array($field, ['account_side', 'report_type', 'expenses_type'])) {
                        $enumClass = match($field) {
                            'account_side' => AccountSide::class,
                            'report_type' => ReportType::class,
                            'expenses_type' => ExpensesType::class,
                        };
                        $updateData[$field] = $enumClass::from($data[$field]);
                    } else {
                        $updateData[$field] = $data[$field];
                    }
                }
            }

            $account->update($updateData);

            return $this->formatAccountResponse($account->fresh());
        });
    }

    public function deleteAccount(string $id, string $companyId, ?string $branchId = null): void
    {
        $account = $this->repository->findById($id, $companyId, $branchId);
        
        if (!$account) {
            throw ValidationException::withMessages([
                'account' => 'Account not found.'
            ]);
        }

        if ($this->repository->hasChildren($id)) {
            throw ValidationException::withMessages([
                'account' => 'Cannot delete account: it has child accounts.'
            ]);
        }

        if ($this->repository->hasPostedEntries($id)) {
            throw ValidationException::withMessages([
                'account' => 'Cannot delete account: it has posted entries.'
            ]);
        }

        $account->delete();
    }

    private function validateParentChange(COAAccount $account, ?string $newParentId): void
    {
        
        if ($newParentId) {
            $newParent = COAAccount::find($newParentId);
            
            if (!$newParent) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Parent account not found.'
                ]);
            }

            
            $current = $newParent;
            while ($current->parent_id) {
                if ($current->parent_id === $account->id) {
                    throw ValidationException::withMessages([
                        'parent_id' => 'Cannot set parent to a descendant account.'
                    ]);
                }
                $current = COAAccount::find($current->parent_id);
            }
        }
    }

    private function formatAccountResponse(COAAccount $account): array
    {
        return [
            'id' => $account->id,
            'code' => $account->code,
            'name' => $account->name,
            'description' => $account->description,
            'account_side' => $account->account_side->value,
            'report_type' => $account->report_type->value,
            'expenses_type' => $account->expenses_type->value,
            'account_level' => $account->account_level,
            'is_posting' => $account->is_posting,
            'is_active' => $account->is_active,
            'parent_id' => $account->parent_id,
            'branch_id' => $account->branch_id,
            'metadata' => $account->metadata,
            'full_path' => $account->getFullPath(),
            'has_children' => $account->hasChildren(),
            'created_at' => $account->created_at,
            'updated_at' => $account->updated_at,
        ];
    }
}