<?php

namespace App\Services\Finance\Reports;

use App\DTO\Finance\AccessScope;
use App\DTO\Finance\ReportContext;
use App\DTO\Finance\ReportResult;
use App\Models\Tenant\COAAccount;
use App\Models\Tenant\JournalEntryRow;
use Illuminate\Support\Facades\DB;

class BalanceSheetReport
{
    public function run(ReportContext $ctx, AccessScope $scope): ReportResult
    {
        $asOfDate = $ctx->asOf ?? $ctx->to ?? now()->format('Y-m-d');

  
        $balanceSheetAccounts = COAAccount::where('report_type', 'balance_sheet')
            ->where('is_active', true)
            ->where(function($query) use ($scope) {
                if (!empty($scope->allowedBranchIds)) {
                    $query->whereIn('branch_id', $scope->allowedBranchIds);
                }
            })
            ->orderBy('code')
            ->get()
            ->keyBy('id');

         
        $accountBalances = $this->calculateAccountBalances($balanceSheetAccounts, $asOfDate, $scope);

       
        $assets = [];
        $liabilities = [];
        $equity = [];

        foreach ($accountBalances as $accountId => $balance) {
            $account = $balanceSheetAccounts[$accountId];
            
            $accountData = [
                'account_code' => $account->code,
                'account_name' => $account->name,
                'account_side' => $account->account_side->value,
                'balance' => $balance,
                'type' => $account->type,
            ];

            switch ($account->type) {
                case 'asset':
                    $assets[] = $accountData;
                    break;
                case 'liability':
                    $liabilities[] = $accountData;
                    break;
                case 'equity':
                    $equity[] = $accountData;
                    break;
            }
        }

       
        $totalAssets = array_sum(array_column($assets, 'balance'));
        $totalLiabilities = array_sum(array_column($liabilities, 'balance'));
        $totalEquity = array_sum(array_column($equity, 'balance'));

        
        $netIncome = $this->calculateNetIncome($asOfDate, $scope);

      
        $equity[] = [
            'account_code' => 'NET_INCOME',
            'account_name' => 'Current Year Net Income',
            'account_side' => 'credit',
            'balance' => $netIncome,
            'type' => 'equity',
        ];

        $totalEquity += $netIncome;

        $data = [
            'assets' => $assets,
            'liabilities' => $liabilities,
            'equity' => $equity,
            'totals' => [
                'total_assets' => $totalAssets,
                'total_liabilities' => $totalLiabilities,
                'total_equity' => $totalEquity,
                'net_income' => $netIncome,
            ],
            'accounting_equation' => [
                'assets' => $totalAssets,
                'liabilities_plus_equity' => $totalLiabilities + $totalEquity,
                'is_balanced' => abs($totalAssets - ($totalLiabilities + $totalEquity)) < 0.01,
            ]
        ];

        return new ReportResult(
            reportKey: 'balance_sheet',
            title: 'Balance Sheet',
            data: $data,
            metadata: [
                'as_of_date' => $asOfDate,
                'total_accounts' => count($accountBalances),
                'asset_accounts' => count($assets),
                'liability_accounts' => count($liabilities),
                'equity_accounts' => count($equity),
                'generated_at' => now()->toISOString(),
            ]
        );
    }

    private function calculateAccountBalances($accounts, $asOfDate, AccessScope $scope): array
    {
        $accountBalances = [];

 
        $journalRows = JournalEntryRow::with(['account'])
            ->whereIn('account_id', $accounts->pluck('id'))
            ->whereHas('journalEntry', function($query) use ($asOfDate, $scope) {
                $query->where('status', 'posted')
                      ->where('issue_date', '<=', $asOfDate);
                
                if (!empty($scope->allowedBranchIds)) {
                    $query->whereIn('branch_id', $scope->allowedBranchIds);
                }
            })
            ->get();

        // Initialize all accounts with zero balance
        foreach ($accounts as $accountId => $account) {
            $accountBalances[$accountId] = 0;
        }

        // Calculate balances based on account side
        foreach ($journalRows as $row) {
            if (!isset($accountBalances[$row->account_id])) {
                continue;
            }

            $account = $accounts[$row->account_id];
            
            if ($account->account_side->value === 'debit') {
                $accountBalances[$row->account_id] += (float) $row->debit - (float) $row->credit;
            } else {
                $accountBalances[$row->account_id] += (float) $row->credit - (float) $row->debit;
            }
        }

        return $accountBalances;
    }

    private function calculateNetIncome($asOfDate, AccessScope $scope): float
    {
        // Get all Income Statement accounts
        $incomeStatementAccounts = COAAccount::where('report_type', 'income_statement')
            ->where('is_active', true)
            ->where(function($query) use ($scope) {
                if (!empty($scope->allowedBranchIds)) {
                    $query->whereIn('branch_id', $scope->allowedBranchIds);
                }
            })
            ->pluck('id');

        if ($incomeStatementAccounts->isEmpty()) {
            return 0;
        }

        // Calculate net income (Revenue - Expenses)
        $netIncome = JournalEntryRow::whereHas('journalEntry', function($query) use ($asOfDate, $scope) {
                $query->where('status', 'posted')
                      ->where('issue_date', '<=', $asOfDate);
                
                if (!empty($scope->allowedBranchIds)) {
                    $query->whereIn('branch_id', $scope->allowedBranchIds);
                }
            })
            ->whereIn('account_id', $incomeStatementAccounts)
            ->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')
            ->first();
 
        $totalRevenue = (float) ($netIncome->total_credit ?? 0);
        $totalExpenses = (float) ($netIncome->total_debit ?? 0);

        return $totalRevenue - $totalExpenses;
    }
}