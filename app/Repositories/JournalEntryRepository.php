<?php 
namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use App\Models\Tenant\JournalEntry;
use App\Enums\JournalStatus;
use App\Enums\JournalEntryType;
use Illuminate\Database\Eloquent\Collection;

class JournalEntryRepository
{
    public function findById(string $id, string $companyId, ?string $branchId = null): ?JournalEntry
    {
        $query = JournalEntry::with(['rows.account', 'branch', 'creator', 'poster'])
            ->where('company_id', $companyId);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        } else {
            $query->whereNull('branch_id');
        }

        return $query->find($id);
    }

    public function listFiltered(
        ?string $from = null,
        ?string $to = null,
        ?string $branchId = null,
        ?string $companyId = null,
        ?JournalStatus $status = null,
        ?string $type = null
    ): Collection {
        $query = JournalEntry::with(['branch', 'creator'])
            ->orderBy('issue_date', 'desc')
            ->orderBy('journal_entry_number', 'desc');

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        if ($from && $to) {
            $query->whereBetween('issue_date', [$from, $to]);
        } elseif ($from) {
            $query->where('issue_date', '>=', $from);
        } elseif ($to) {
            $query->where('issue_date', '<=', $to);
        }

        if ($branchId) {
            $query->where('branch_id', $branchId);
        } elseif ($branchId === null) {
            $query->whereNull('branch_id');
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($type) {
            $query->where('journal_entry_type', $type);
        }

        return $query->get();
    }

    public function generateJournalNumber(string $companyId, ?string $branchId, int $year): string
    {
        $count = JournalEntry::where('company_id', $companyId)
            ->where('finance_year', $year)
            ->byBranch($branchId)
            ->count() + 1;

        $prefix = $branchId ? 'JE' : 'JE';  
        return $prefix . '-' . $year . '-' . str_pad($count, 6, '0', STR_PAD_LEFT);
    }

    public function save(JournalEntry $entry): void
    {
        $entry->save();
    }

    public function hasPostedEntriesForAccount(string $accountId): bool
    {
        return \DB::table('journal_entry_rows as r')
            ->join('journal_entries as h', 'r.journal_entry_id', '=', 'h.id')
            ->where('r.account_id', $accountId)
            ->where('h.status', JournalStatus::POSTED)
            ->exists();
    }
}