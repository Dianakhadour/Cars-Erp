<?php

namespace App\Services\Finance\Reports;

use App\DTO\Finance\AccessScope;
use App\DTO\Finance\ReportContext;
use App\DTO\Finance\ReportResult;
use App\Models\Tenant\COAAccount;
use App\Models\Tenant\JournalEntryRow;
use Illuminate\Support\Facades\DB;

class IncomeStatementReport
{
    public function run(ReportContext $ctx, AccessScope $scope): ReportResult
    {
        $from = $ctx->from ?? $ctx->asOf ?? now()->subMonth()->format('Y-m-d');
        $to = $ctx->to ?? $ctx->asOf ?? now()->format('Y-m-d');

        // Get all Income Statement accounts
        $incomeStatementAccounts = COAAccount::where('report_type', 'income_statement')
            ->where('is_active', true)
            ->where(function($query) use ($scope) {
                if (!empty($scope->allowedBranchIds)) {
                    $query->whereIn('branch_id', $scope->allowedBranchIds);
                }
            })
            ->orderBy('code')
            ->get()
            ->keyBy('id');

        // Calculate account balances for the period
        $accountBalances = $this->calculateAccountBalances($incomeStatementAccounts, $from, $to, $scope);

        // Organize by account type
        $revenues = [];
        $expenses = [];

        foreach ($accountBalances as $accountId => $balance) {
            $account = $incomeStatementAccounts[$accountId];
            
            $accountData = [
                'account_code' => $account->code,
                'account_name' => $account->name,
                'account_side' => $account->account_side->value,
                'balance' => $balance,
                'type' => $account->type,
            ];

            if ($account->type === 'revenue') {
                $revenues[] = $accountData;
            } elseif ($account->type === 'expense') {
                $expenses[] = $accountData;
            }
        }

      
        $totalRevenue = array_sum(array_column($revenues, 'balance'));
        $totalExpenses = array_sum(array_column($expenses, 'balance'));
        $netIncome = $totalRevenue - $totalExpenses;

        $data = [
            'revenues' => $revenues,
            'expenses' => $expenses,
            'totals' => [
                'total_revenue' => $totalRevenue,
                'total_expenses' => $totalExpenses,
                'net_income' => $netIncome,
                'gross_profit' => $totalRevenue,  
                'operating_income' => $netIncome,  
            ],
            'profitability_metrics' => [
                'gross_margin' => $totalRevenue > 0 ? ($totalRevenue / $totalRevenue) * 100 : 0,
                'net_margin' => $totalRevenue > 0 ? ($netIncome / $totalRevenue) * 100 : 0,
                'expense_ratio' => $totalRevenue > 0 ? ($totalExpenses / $totalRevenue) * 100 : 0,
            ]
        ];

        return new ReportResult(
            reportKey: 'income_statement',
            title: 'Income Statement (Profit & Loss)',
            data: $data,
            metadata: [
                'period' => "From {$from} to {$to}",
                'total_accounts' => count($accountBalances),
                'revenue_accounts' => count($revenues),
                'expense_accounts' => count($expenses),
                'generated_at' => now()->toISOString(),
            ]
        );
    }

    private function calculateAccountBalances($accounts, $from, $to, AccessScope $scope): array
    {
        $accountBalances = [];

        
        $journalRows = JournalEntryRow::with(['account'])
            ->whereIn('account_id', $accounts->pluck('id'))
            ->whereHas('journalEntry', function($query) use ($from, $to, $scope) {
                $query->where('status', 'posted')
                      ->whereBetween('issue_date', [$from, $to]);
                
                if (!empty($scope->allowedBranchIds)) {
                    $query->whereIn('branch_id', $scope->allowedBranchIds);
                }
            })
            ->get();

       
        foreach ($accounts as $accountId => $account) {
            $accountBalances[$accountId] = 0;
        }

       
        foreach ($journalRows as $row) {
            if (!isset($accountBalances[$row->account_id])) {
                continue;
            }

            $account = $accounts[$row->account_id];
            
            // For Income Statement accounts:
            // - Revenue accounts: credit increases revenue, debit decreases
            // - Expense accounts: debit increases expense, credit decreases
            if ($account->type === 'revenue') {
                // Revenue accounts are credit-side
                $accountBalances[$row->account_id] += (float) $row->credit - (float) $row->debit;
            } elseif ($account->type === 'expense') {
                // Expense accounts are debit-side  
                $accountBalances[$row->account_id] += (float) $row->debit - (float) $row->credit;
            }
        }

        return $accountBalances;
    }
}