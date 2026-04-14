<?php

namespace App\Services\Finance\Reports;

use App\DTO\Finance\AccessScope;
use App\DTO\Finance\ReportContext;
use App\DTO\Finance\ReportResult;
use App\Models\Tenant\JournalEntry;
use App\Models\Tenant\JournalEntryRow;
use Illuminate\Support\Facades\DB;

class JournalEntriesReport
{
    public function run(ReportContext $ctx, AccessScope $scope): ReportResult
    {
        $from = $ctx->from ?? $ctx->asOf ?? now()->subMonth()->format('Y-m-d');
        $to = $ctx->to ?? $ctx->asOf ?? now()->format('Y-m-d');

        // Get journal entries with their rows and related data
        $journalEntries = JournalEntry::with([
            'rows.account',
            'createdByUser',
            'postedByUser'
        ])
        ->whereBetween('issue_date', [$from, $to])
        ->where(function($query) use ($scope) {
            if (!empty($scope->allowedBranchIds)) {
                $query->whereIn('branch_id', $scope->allowedBranchIds);
            }
        })
        // ✅ FIX: Use filters from context instead of undefined properties
        ->when(isset($ctx->filters['status']), function($query) use ($ctx) {
            $query->where('status', $ctx->filters['status']);
        })
        ->when(isset($ctx->filters['journal_entry_type']), function($query) use ($ctx) {
            $query->where('journal_entry_type', $ctx->filters['journal_entry_type']);
        })
        ->orderBy('issue_date', 'desc')
        ->orderBy('journal_entry_number', 'desc')
        ->get();

        $data = [];
        $summary = [
            'total_entries' => 0,
            'posted_entries' => 0,
            'draft_entries' => 0,
            'total_debit' => 0,
            'total_credit' => 0,
            'entry_types' => []
        ];

        foreach ($journalEntries as $entry) {
            $entryData = [
                'journal_entry_id' => $entry->id,
                'journal_entry_number' => $entry->journal_entry_number,
                'issue_date' => $entry->issue_date->format('Y-m-d'),
                'posting_date' => $entry->posting_date->format('Y-m-d'),
                'journal_entry_type' => $entry->journal_entry_type->value,
                'journal_entry_by' => $entry->journal_entry_by->value,
                'status' => $entry->status->value,
                'narration' => $entry->narration,
                'document_ref' => $entry->document_ref,
                'total_debit' => (float) $entry->total_debit,
                'total_credit' => (float) $entry->total_credit,
                'currency' => $entry->currency,
                'created_by' => $entry->createdByUser ? $entry->createdByUser->name : 'System',
                'posted_by' => $entry->postedByUser ? $entry->postedByUser->name : null,
                'posted_at' => $entry->posted_at?->format('Y-m-d H:i:s'),
                'rows' => []
            ];

            // Add row details
            foreach ($entry->rows as $row) {
                // ✅ FIX: Safe access to account properties
                if (!$row->account) {
                    continue;
                }

                $rowData = [
                    'line_number' => $row->line_number,
                    'account_code' => $row->account->code,
                    'account_name' => $row->account->name,
                    'account_side' => $row->account->account_side instanceof \App\Enums\AccountSide 
                        ? $row->account->account_side->value 
                        : (string) $row->account->account_side,
                    'debit' => (float) $row->debit,
                    'credit' => (float) $row->credit,
                    'entry_type' => $row->entry_type instanceof \App\Enums\EntryType 
                        ? $row->entry_type->value 
                        : (string) $row->entry_type,
                    'reference' => $row->reference,
                    'issue_date' => $row->issue_date->format('Y-m-d'),
                ];

                $entryData['rows'][] = $rowData;
            }

            $data[] = $entryData;

            // Update summary
            $summary['total_entries']++;
            $summary['total_debit'] += (float) $entry->total_debit;
            $summary['total_credit'] += (float) $entry->total_credit;
            
            if ($entry->status->value === 'posted') {
                $summary['posted_entries']++;
            } elseif ($entry->status->value === 'draft') {
                $summary['draft_entries']++;
            }

            // Track entry types
            $entryType = $entry->journal_entry_type->value;
            if (!isset($summary['entry_types'][$entryType])) {
                $summary['entry_types'][$entryType] = 0;
            }
            $summary['entry_types'][$entryType]++;
        }

        return new ReportResult(
            reportKey: 'journal_entries',
            title: 'Journal Entries Report',
            data: $data,
            metadata: [
                'period' => "From {$from} to {$to}",
                'summary' => $summary,
                'generated_at' => now()->toISOString(),
            ]
        );
    }
}