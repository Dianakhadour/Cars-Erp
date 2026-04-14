<?php

namespace App\Services\Finance\Reports;

use App\DTO\Finance\AccessScope;
use App\DTO\Finance\ReportContext;
use App\DTO\Finance\ReportResult;
use App\Models\Tenant\COAAccount;
use App\Models\Tenant\JournalEntry;
use App\Models\Tenant\JournalEntryRow;
use Illuminate\Support\Facades\DB;

class AccountLedgerReport
{
    public function run(ReportContext $ctx, AccessScope $scope): ReportResult
    {
        $from = $ctx->from ?? $ctx->asOf ?? now()->subMonth()->format('Y-m-d');
        $to = $ctx->to ?? $ctx->asOf ?? now()->format('Y-m-d');
        
      
        $accountCode = $ctx->parameters['parameters']['account_code'] 
                    ?? $ctx->parameters['account_code'] 
                    ?? null;
                    
        $accountId = $ctx->parameters['parameters']['account_id'] 
                  ?? $ctx->parameters['account_id'] 
                  ?? null;

        if (!$accountCode && !$accountId) {
            throw new \InvalidArgumentException("Account code or ID is required for account ledger report. Use 'account_code' in parameters.");
        }

        // Find the account
        $accountQuery = COAAccount::where('is_active', true)
            ->where(function($query) use ($scope) {
                if (!empty($scope->allowedBranchIds)) {
                    $query->whereIn('branch_id', $scope->allowedBranchIds);
                }
            });

        if ($accountCode) {
            $account = $accountQuery->where('code', $accountCode)->first();
        } else {
            $account = $accountQuery->where('id', $accountId)->first();
        }

        if (!$account) {
            throw new \InvalidArgumentException("Account not found or access denied");
        }

        // Get opening balance (balance before the from date)
        $openingBalance = $this->calculateOpeningBalance($account, $from, $scope);

        // Get all transactions for this account in the period
        $transactions = JournalEntryRow::with([
            'journalEntry',
            'account'
        ])
        ->where('account_id', $account->id)
        ->whereHas('journalEntry', function($query) use ($from, $to, $scope) {
            $query->where('status', 'posted')
                  ->whereBetween('issue_date', [$from, $to]);
            
            if (!empty($scope->allowedBranchIds)) {
                $query->whereIn('branch_id', $scope->allowedBranchIds);
            }
        })
        ->orderBy('issue_date')
        ->orderBy('journal_entry_id')
        ->orderBy('line_number')
        ->get();

        $data = [];
        $runningBalance = $openingBalance;

        foreach ($transactions as $transaction) {
            // Calculate transaction effect based on account side
            if ($account->account_side->value === 'debit') {
                $transactionAmount = (float) $transaction->debit - (float) $transaction->credit;
            } else {
                $transactionAmount = (float) $transaction->credit - (float) $transaction->debit;
            }

            $runningBalance += $transactionAmount;

            $data[] = [
                'date' => $transaction->issue_date->format('Y-m-d'),
                'journal_entry_number' => $transaction->journalEntry->journal_entry_number,
                'journal_entry_type' => $transaction->journalEntry->journal_entry_type->value,
                'narration' => $transaction->journalEntry->narration,
                'reference' => $transaction->reference,
                'debit' => (float) $transaction->debit,
                'credit' => (float) $transaction->credit,
                'transaction_amount' => $transactionAmount,
                'running_balance' => $runningBalance,
                'entry_type' => $transaction->entry_type->value,
            ];
        }

        // Calculate summary
        $totalDebit = $transactions->sum('debit');
        $totalCredit = $transactions->sum('credit');
        
        if ($account->account_side->value === 'debit') {
            $netChange = $totalDebit - $totalCredit;
        } else {
            $netChange = $totalCredit - $totalDebit;
        }

        $closingBalance = $openingBalance + $netChange;

        $summary = [
            'account' => [
                'code' => $account->code,
                'name' => $account->name,
                'account_side' => $account->account_side->value,
                'type' => $account->type,
                'report_type' => $account->report_type,
            ],
            'period' => [
                'from' => $from,
                'to' => $to,
            ],
            'balances' => [
                'opening_balance' => $openingBalance,
                'closing_balance' => $closingBalance,
                'net_change' => $netChange,
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
            ],
            'activity' => [
                'total_transactions' => count($data),
                'debit_transactions' => $transactions->where('debit', '>', 0)->count(),
                'credit_transactions' => $transactions->where('credit', '>', 0)->count(),
            ]
        ];

        return new ReportResult(
            reportKey: 'account_ledger',
            title: 'Account Ledger - ' . $account->code . ' - ' . $account->name,
            data: $data,
            metadata: [
                'summary' => $summary,
                'generated_at' => now()->toISOString(),
            ]
        );
    }

    private function calculateOpeningBalance(COAAccount $account, $startDate, AccessScope $scope): float
    {
        // Calculate balance from all transactions before the start date
        $openingRows = JournalEntryRow::where('account_id', $account->id)
            ->whereHas('journalEntry', function($query) use ($startDate, $scope) {
                $query->where('status', 'posted')
                      ->where('issue_date', '<', $startDate);
                
                if (!empty($scope->allowedBranchIds)) {
                    $query->whereIn('branch_id', $scope->allowedBranchIds);
                }
            })
            ->get();

        $openingBalance = 0;

        foreach ($openingRows as $row) {
            if ($account->account_side->value === 'debit') {
                $openingBalance += (float) $row->debit - (float) $row->credit;
            } else {
                $openingBalance += (float) $row->credit - (float) $row->debit;
            }
        }

        return $openingBalance;
    }
}