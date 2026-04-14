<?php
 
namespace App\Services\Finance;

class ReportRegistry
{
    private array $reports = [];

    public function __construct()
    {
        $this->registerDefaultReports();
    }

    public function register(string $key, array $definition): void
    {
        $this->reports[$key] = $definition;
    }

    public function get(string $key): ?array
    {
        return $this->reports[$key] ?? null;
    }

    public function all(): array
    {
        return $this->reports;
    }

    private function registerDefaultReports(): void
    {
        // Trial Balance
        $this->register('trial_balance', [
            'key' => 'trial_balance',
            'title' => 'Trial Balance',
            'description' => 'Shows debit and credit balances for all accounts',
            'category' => 'financial',
            'permission' => 'finance.reports.trial_balance',
            'handler' => \App\Services\Finance\Reports\TrialBalanceReport::class,
        ]);

        // General Ledger
        $this->register('general_ledger', [
            'key' => 'general_ledger',
            'title' => 'General Ledger',
            'description' => 'Detailed transaction history for all accounts',
            'category' => 'ledger',
            'permission' => 'finance.reports.general_ledger',
            'handler' => \App\Services\Finance\Reports\GeneralLedgerReport::class,
        ]);

        // Balance Sheet
        $this->register('balance_sheet', [
            'key' => 'balance_sheet',
            'title' => 'Balance Sheet',
            'description' => 'Assets, Liabilities and Equity report',
            'category' => 'financial',
            'permission' => 'finance.reports.balance_sheet',
            'handler' => \App\Services\Finance\Reports\BalanceSheetReport::class,
        ]);

        // Income Statement
        $this->register('income_statement', [
            'key' => 'income_statement',
            'title' => 'Income Statement',
            'description' => 'Revenue and Expenses report',
            'category' => 'financial',
            'permission' => 'finance.reports.income_statement',
            'handler' => \App\Services\Finance\Reports\IncomeStatementReport::class,
        ]);

        // Journal Entries Report
        $this->register('journal_entries', [
            'key' => 'journal_entries',
            'title' => 'Journal Entries Report',
            'description' => 'All journal entries with details',
            'category' => 'ledger',
            'permission' => 'finance.reports.journal_entries',
            'handler' => \App\Services\Finance\Reports\JournalEntriesReport::class,
        ]);

        // Account Ledger
        $this->register('account_ledger', [
            'key' => 'account_ledger',
            'title' => 'Account Ledger',
            'description' => 'Transaction history for specific account',
            'category' => 'ledger',
            'permission' => 'finance.reports.account_ledger',
            'handler' => \App\Services\Finance\Reports\AccountLedgerReport::class,
        ]);
    }
}