<?php
 
namespace App\Services\Finance\Reports;

use App\DTO\Finance\AccessScope;
use App\DTO\Finance\ReportContext;
use App\DTO\Finance\ReportResult;
use App\Models\Tenant\COAAccount;
use App\Models\Tenant\JournalEntry;
use App\Models\Tenant\JournalEntryRow;
use Illuminate\Support\Facades\DB;

class GeneralLedgerReport
{
    public function run(ReportContext $ctx, AccessScope $scope): ReportResult
    {
        $from = $ctx->from;
        $to = $ctx->to;

        // Get all journal entry rows for the period with related data
        $journalRows = JournalEntryRow::with([
            'journalEntry', 
            'account'
        ])
        ->whereHas('journalEntry', function($query) use ($from, $to, $scope) {
            $query->where('status', 'posted')
                  ->whereBetween('issue_date', [$from, $to]);
            
            // Apply branch filtering
            if (!empty($scope->allowedBranchIds)) {
                $query->whereIn('branch_id', $scope->allowedBranchIds);
            }
        })
        ->orderBy('issue_date')
        ->orderBy('journal_entry_id')
        ->orderBy('line_number')
        ->get();
 
        $data = [];
        $runningBalances = [];
       
        foreach ($journalRows as $row) {
           
            $accountCode = $row->account->code;
            $accountName = $row->account->name;
         
            // Initialize running balance for account if not set
            if (!isset($runningBalances[$accountCode])) {
                $runningBalances[$accountCode] = 0;
            }

            // Calculate transaction amount and update running balance
            if ($row->account->account_side->value === 'debit') {
                $transactionAmount = $row->debit - $row->credit;
                $runningBalances[$accountCode] += $transactionAmount;
            } else {
                $transactionAmount = $row->credit - $row->debit;
                $runningBalances[$accountCode] += $transactionAmount;
            }
            
            $data[] = [
                'date' => $row->issue_date->format('Y-m-d'),
                'journal_entry_number' => $row->journalEntry->journal_entry_number,
                'account_code' => $accountCode,
                'account_name' => $accountName,
                'account_side' => $row->account->account_side->value,
                'debit' => (float) $row->debit,
                'credit' => (float) $row->credit,
                'transaction_amount' => (float) $transactionAmount,
                'running_balance' => (float) $runningBalances[$accountCode],
                'description' => $row->journalEntry->narration,
                'reference' => $row->reference,
                'entry_type' => $row->entry_type->value,
            ];
        }

        
     
        $accountSummary = [];
        foreach ($runningBalances as $accountCode => $balance) {
            $accountRows = array_filter($data, fn($item) => $item['account_code'] === $accountCode);
            
            if (empty($accountRows)) {
                continue;
            }
            
         
            $firstRow = $accountRows[array_key_first($accountRows)];
            
            $accountSummary[] = [
                'account_code' => $accountCode,
                'account_name' => $firstRow['account_name'],
                'account_side' => $firstRow['account_side'],
                'total_debit' => array_sum(array_column($accountRows, 'debit')),
                'total_credit' => array_sum(array_column($accountRows, 'credit')),
                'ending_balance' => $balance,
                'transaction_count' => count($accountRows),
            ];
        }

        return new ReportResult(
            reportKey: 'general_ledger',
            title: 'General Ledger Report',
            data: $data,
            metadata: [
                'period' => "From {$from} to {$to}",
                'total_transactions' => count($data),
                'total_accounts' => count($accountSummary),
                'total_debit' => array_sum(array_column($data, 'debit')),
                'total_credit' => array_sum(array_column($data, 'credit')),
                'account_summary' => $accountSummary,
                'generated_at' => now()->toISOString(),
            ]
        );
    }
}