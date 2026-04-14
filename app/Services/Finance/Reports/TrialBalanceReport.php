<?php
// app/Services/Finance/Reports/TrialBalanceReport.php

namespace App\Services\Finance\Reports;

use App\DTO\Finance\AccessScope;
use App\DTO\Finance\ReportContext;
use App\DTO\Finance\ReportResult;
use App\Models\Tenant\COAAccount;
use App\Models\Tenant\JournalEntry;
use App\Models\Tenant\JournalEntryRow;
use Illuminate\Support\Facades\DB;

class TrialBalanceReport
{
    public function run(ReportContext $ctx, AccessScope $scope): ReportResult
    {
        $from = $ctx->from;
        $to = $ctx->to;
// dd( $from, $to);
        // Get all active accounts with their journal entry rows for the period
        $accounts = COAAccount::where('is_active', true)
            ->with(['journalEntryRows' => function($query) use ($from, $to, $scope) {
                $query->whereHas('journalEntry', function($q) use ($from, $to, $scope) {
                    $q->where('status', 'posted')
                      ->whereBetween('issue_date', [$from, $to]);
                    
                    // Apply branch filtering if specified
                    if (!empty($scope->allowedBranchIds)) {
                        $q->whereIn('branch_id', $scope->allowedBranchIds);
                    }
                });
            }])
            ->orderBy('code')
            ->get();
 
        $data = [];
        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($accounts as $account) {
            $debit = $account->journalEntryRows->sum('debit');
            $credit = $account->journalEntryRows->sum('credit');
            
            // Skip accounts with no activity in the period
            if ($debit == 0 && $credit == 0) {
                continue;
            }

          //   dd($account->account_side);
            if ($account->account_side->value === 'debit') {
                $balance = $debit - $credit;
            } else {
                $balance = $credit - $debit; 
            }

            $data[] = [
                'account_code' => $account->code,
                'account_name' => $account->name,
                'account_type' => $account->type,
                'account_side' => $account->account_side,
                'debit' => (float) $debit,
                'credit' => (float) $credit,
                'balance' => (float) $balance,
                'report_type' => $account->report_type,
            ];

            $totalDebit += $debit;
            $totalCredit += $credit;
        }

        return new ReportResult(
            reportKey: 'trial_balance',
            title: 'Trial Balance Report',
            data: $data,
            metadata: [
                'period' => "From {$from} to {$to}",
                'total_debit' => (float) $totalDebit,
                'total_credit' => (float) $totalCredit,
                'is_balanced' => abs($totalDebit - $totalCredit) < 0.01,
                'difference' => (float) abs($totalDebit - $totalCredit),
                'total_accounts' => count($data),
                'generated_at' => now()->toISOString(),
            ]
        );
    }
}