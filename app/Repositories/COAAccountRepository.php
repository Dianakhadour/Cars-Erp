<?php 
namespace App\Repositories;

use App\Models\Tenant\COAAccount;
use Illuminate\Database\Eloquent\Collection;

class COAAccountRepository
{
    public function findById(string $id, string $companyId, ?string $branchId = null): ?COAAccount
    {
        $query = COAAccount::with(['journalEntryRows.journalEntry'])  
        ->where('company_id', $companyId);
        
        if ($branchId) {
            $query->where('branch_id', $branchId);
        } else {
            $query->whereNull('branch_id');
        }
        
        return $query->find($id);
    }

    public function getAccountsTree(?string $parentId = null, ?int $level = null, ?string $branchId = null): Collection
    {
        $query = COAAccount::active()->with('children');
        
        if ($parentId) {
            $query->where('parent_id', $parentId);
        } else {
            $query->rootAccounts();
        }
        
        if ($level !== null) {
            $query->where('account_level', $level);
        }
        
        if ($branchId) {
            $query->where('branch_id', $branchId);
        } else {
            $query->whereNull('branch_id');
        }
        
        return $query->orderBy('code')->get();
    }

    public function accountNumberExists(string $code, string $companyId, ?string $branchId = null): bool
    {
        $query = COAAccount::where('company_id', $companyId)
            ->where('code', $code);
            
        if ($branchId) {
            $query->where('branch_id', $branchId);
        } else {
            $query->whereNull('branch_id');
        }
        
        return $query->exists();
    }

    public function hasChildren(string $accountId): bool
    {
        return COAAccount::where('parent_id', $accountId)->exists();
    }

    public function hasPostedEntries(string $accountId): bool
    {
            // Temporary

    }

    public function updateAccountLevels(): void
    {
        
        $accounts = COAAccount::all();
        
        foreach ($accounts as $account) {
            $level = 1;
            $current = $account;
            
            while ($current->parent) {
                $level++;
                $current = $current->parent;
            }
            
            if ($account->account_level !== $level) {
                $account->update(['account_level' => $level]);
            }
        }
    }
}