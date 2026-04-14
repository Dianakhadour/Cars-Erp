<?php

namespace App\Repositories;

use App\Models\Tenant\JournalEntryRow;
use Illuminate\Database\Eloquent\Collection;

class JournalEntryRowRepository
{
    public function getRowsByJournalEntry(string $journalEntryId): Collection
    {
        return JournalEntryRow::with('account')
            ->where('journal_entry_id', $journalEntryId)
            ->orderBy('line_number')
            ->get();
    }

    public function save(JournalEntryRow $row): void
    {
        $row->save();
    }

    public function getAccountEntries(string $accountId, string $from, string $to, ?string $branchId = null): Collection
    {
        $query = JournalEntryRow::with(['journalEntry'])
            ->where('account_id', $accountId)
            ->whereBetween('issue_date', [$from, $to])
            ->whereHas('journalEntry', function ($q) {
                $q->where('status', 'posted');
            })
            ->orderBy('issue_date')
            ->orderBy('journal_entry_id')
            ->orderBy('line_number');

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        return $query->get();
    }
}