<?php

namespace App\Exports;

use App\DTO\Finance\ReportResult;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class FinancialReportExport implements FromArray, WithHeadings, WithTitle, WithStyles
{
    public function __construct(private ReportResult $reportResult) {}

    public function array(): array
    {
        return $this->transformReportData();
    }

    public function headings(): array
    {
        return $this->getHeadingsForReport();
    }

    public function title(): string
    {
        return $this->getSheetTitle();
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
            'A:Z' => ['alignment' => ['vertical' => 'center']],
        ];
    }

    private function transformReportData(): array
    {
        $data = [];
        
        switch ($this->reportResult->reportKey) {
            case 'general_ledger':
                $data = $this->transformGeneralLedger();
                break;
            case 'balance_sheet':
                $data = $this->transformBalanceSheet();
                break;
            case 'income_statement':
                $data = $this->transformIncomeStatement();
                break;
            case 'trial_balance':
                $data = $this->transformTrialBalance();
                break;
            case 'journal_entries':
                $data = $this->transformJournalEntries();
                break;
            case 'account_ledger':
                $data = $this->transformAccountLedger();
                break;
        }

        return $data;
    }

    //  TRIAL BALANCE - 
    private function transformTrialBalance(): array
    {
        $data = [];
        
        foreach ($this->reportResult->data as $account) {
            $accountSide = $account['account_side'] instanceof \App\Enums\AccountSide 
                ? $account['account_side']->value 
                : (string) $account['account_side']; 
            
            $data[] = [
                $account['account_code'],
                $account['account_name'],
                $accountSide,
                $account['debit'] ?? 0,
                $account['credit'] ?? 0,
                $account['balance'] ?? 0
            ];
        }

        // Summary section
        $data[] = [''];
        $data[] = ['SUMMARY'];
        $data[] = ['Total Debit', '', '', $this->reportResult->metadata['total_debit'] ?? 0];
        $data[] = ['Total Credit', '', '', '', $this->reportResult->metadata['total_credit'] ?? 0];
        $data[] = ['Difference', '', '', '', '', 
                  ($this->reportResult->metadata['total_debit'] ?? 0) - ($this->reportResult->metadata['total_credit'] ?? 0)];
        $data[] = ['Status', $this->reportResult->metadata['is_balanced'] ? 'BALANCED' : 'NOT BALANCED'];
        $data[] = ['Total Accounts', $this->reportResult->metadata['total_accounts'] ?? 0];

        return $data;
    }

    // ✅ GENERAL LEDGER
    private function transformGeneralLedger(): array
    {
        $data = [];
        
        foreach ($this->reportResult->data as $row) {
            $data[] = [
                $row['date'],
                $row['journal_entry_number'],
                $row['account_code'],
                $row['account_name'],
                $row['debit'],
                $row['credit'],
                $row['transaction_amount'],
                $row['running_balance'],
                $row['description'] ?? ''
            ];
        }
        
        // Add summary rows
        $data[] = [''];
        $data[] = ['SUMMARY'];
        $data[] = ['Total Debit', '', '', '', $this->reportResult->metadata['total_debit'] ?? 0];
        $data[] = ['Total Credit', '', '', '', '', $this->reportResult->metadata['total_credit'] ?? 0];
        $data[] = ['Total Transactions', $this->reportResult->metadata['total_transactions'] ?? 0];
        
        return $data;
    }

    // ✅ BALANCE SHEET
    private function transformBalanceSheet(): array
    {
        $data = [];
        $reportData = $this->reportResult->data;

        // Assets section
        $data[] = ['ASSETS'];
        $data[] = ['Account Code', 'Account Name', 'Balance'];
        foreach ($reportData['assets'] as $asset) {
            $data[] = [
                $asset['account_code'],
                $asset['account_name'],
                $asset['balance']
            ];
        }
        $data[] = ['Total Assets', '', $reportData['totals']['total_assets']];
        $data[] = [''];

        // Liabilities section
        $data[] = ['LIABILITIES'];
        $data[] = ['Account Code', 'Account Name', 'Balance'];
        foreach ($reportData['liabilities'] as $liability) {
            $data[] = [
                $liability['account_code'],
                $liability['account_name'],
                $liability['balance']
            ];
        }
        $data[] = ['Total Liabilities', '', $reportData['totals']['total_liabilities']];
        $data[] = [''];

        // Equity section
        $data[] = ['EQUITY'];
        $data[] = ['Account Code', 'Account Name', 'Balance'];
        foreach ($reportData['equity'] as $equity) {
            $data[] = [
                $equity['account_code'],
                $equity['account_name'],
                $equity['balance']
            ];
        }
        $data[] = ['Total Equity', '', $reportData['totals']['total_equity']];
        $data[] = [''];

        // Accounting equation
        $data[] = ['ACCOUNTING EQUATION'];
        $data[] = ['Assets', $reportData['accounting_equation']['assets']];
        $data[] = ['Liabilities + Equity', $reportData['accounting_equation']['liabilities_plus_equity']];
        $data[] = ['Balanced', $reportData['accounting_equation']['is_balanced'] ? 'YES' : 'NO'];

        return $data;
    }

    // ✅ INCOME STATEMENT
    private function transformIncomeStatement(): array
    {
        $data = [];
        $reportData = $this->reportResult->data;

        // Revenues section
        $data[] = ['REVENUES'];
        $data[] = ['Account Code', 'Account Name', 'Amount'];
        foreach ($reportData['revenues'] as $revenue) {
            $data[] = [
                $revenue['account_code'],
                $revenue['account_name'],
                $revenue['balance']
            ];
        }
        $data[] = ['Total Revenue', '', $reportData['totals']['total_revenue']];
        $data[] = [''];

        // Expenses section
        $data[] = ['EXPENSES'];
        $data[] = ['Account Code', 'Account Name', 'Amount'];
        foreach ($reportData['expenses'] as $expense) {
            $data[] = [
                $expense['account_code'],
                $expense['account_name'],
                $expense['balance']
            ];
        }
        $data[] = ['Total Expenses', '', $reportData['totals']['total_expenses']];
        $data[] = [''];

        // Net Income calculation
        $data[] = ['NET INCOME CALCULATION'];
        $data[] = ['Total Revenue', $reportData['totals']['total_revenue']];
        $data[] = ['Less: Total Expenses', $reportData['totals']['total_expenses']];
        $data[] = ['Net Income', $reportData['totals']['net_income']];
        $data[] = [''];

        // Profitability metrics
        $data[] = ['PROFITABILITY METRICS'];
        $data[] = ['Net Margin', ($reportData['profitability_metrics']['net_margin'] ?? 0) . '%'];
        $data[] = ['Expense Ratio', ($reportData['profitability_metrics']['expense_ratio'] ?? 0) . '%'];

        return $data;
    }

    //  JOURNAL ENTRIES  
    private function transformJournalEntries(): array
    {
        $data = [];
        $summary = $this->reportResult->metadata['summary'] ?? [];

        // Global summary first
        $data[] = ['REPORT SUMMARY'];
        $data[] = ['Total Journal Entries', $summary['total_entries'] ?? 0];
        $data[] = ['Posted Entries', $summary['posted_entries'] ?? 0];
        $data[] = ['Draft Entries', $summary['draft_entries'] ?? 0];
        $data[] = ['Total Debit', $summary['total_debit'] ?? 0];
        $data[] = ['Total Credit', $summary['total_credit'] ?? 0];
        $data[] = [''];

        // Entry types breakdown
        if (isset($summary['entry_types'])) {
            $data[] = ['ENTRY TYPES BREAKDOWN'];
            foreach ($summary['entry_types'] as $type => $count) {
                $data[] = [ucfirst(str_replace('_', ' ', $type)), $count];
            }
            $data[] = [''];
        }

        // Journal entries detail  
        $data[] = ['JOURNAL ENTRIES DETAIL'];
        $data[] = ['Journal Entry', 'Date', 'Type', 'Status', 'Narration', 'Total Debit', 'Total Credit', 'Created By'];

        foreach ($this->reportResult->data as $entry) {
            $entryType = $entry['journal_entry_type'] instanceof \App\Enums\JournalEntryType 
                ? $entry['journal_entry_type']->value 
                : (string) $entry['journal_entry_type'];  
                
            $status = $entry['status'] instanceof \App\Enums\JournalStatus 
                ? $entry['status']->value 
                : (string) $entry['status'];  
                
            $createdBy = $entry['journal_entry_by'] instanceof \App\Enums\JournalEntryBy 
                ? $entry['journal_entry_by']->value 
                : (string) $entry['journal_entry_by'];  
            
            $data[] = [
                $entry['journal_entry_number'],
                $entry['issue_date'],
                $entryType,
                $status,
                $entry['narration'],
                $entry['total_debit'],
                $entry['total_credit'],
                $createdBy
            ];
        }

        return $data;
    }

    
    private function transformAccountLedger(): array
    {
        $data = [];
        $summary = $this->reportResult->metadata['summary'];

        // Account information -  
        $accountSide = $summary['account']['account_side'] instanceof \App\Enums\AccountSide 
            ? $summary['account']['account_side']->value 
            : (string) $summary['account']['account_side'];  
        
        $data[] = ['ACCOUNT INFORMATION'];
        $data[] = ['Account Code', $summary['account']['code']];
        $data[] = ['Account Name', $summary['account']['name']];
        $data[] = ['Account Side', $accountSide];
        $data[] = ['Account Type', $summary['account']['type']];
        $data[] = ['Period', $summary['period']['from'] . ' to ' . $summary['period']['to']];
        $data[] = [''];

        // Transactions - 
        $data[] = ['TRANSACTIONS'];
        $data[] = ['Date', 'Journal Entry', 'Type', 'Narration', 'Reference', 'Debit', 'Credit', 'Amount', 'Running Balance'];

        foreach ($this->reportResult->data as $row) {
            $entryType = $row['journal_entry_type'] instanceof \App\Enums\JournalEntryType 
                ? $row['journal_entry_type']->value 
                : (string) $row['journal_entry_type'];  
            
            $data[] = [
                $row['date'],
                $row['journal_entry_number'],
                $entryType,
                $row['narration'],
                $row['reference'],
                $row['debit'],
                $row['credit'],
                $row['transaction_amount'],
                $row['running_balance']
            ];
        }

        // Account summary
        $data[] = [''];
        $data[] = ['ACCOUNT SUMMARY'];
        $data[] = ['Opening Balance', $summary['balances']['opening_balance']];
        $data[] = ['Closing Balance', $summary['balances']['closing_balance']];
        $data[] = ['Net Change', $summary['balances']['net_change']];
        $data[] = ['Total Debit', $summary['balances']['total_debit']];
        $data[] = ['Total Credit', $summary['balances']['total_credit']];
        $data[] = ['Total Transactions', $summary['activity']['total_transactions']];

        return $data;
    }

    private function getHeadingsForReport(): array
    {
        switch ($this->reportResult->reportKey) {
            case 'general_ledger':
                return ['Date', 'Journal Entry', 'Account Code', 'Account Name', 'Debit', 'Credit', 'Amount', 'Running Balance', 'Description'];
            case 'balance_sheet':
                return ['Section', 'Account Code', 'Account Name', 'Balance'];
            case 'income_statement':
                return ['Section', 'Account Code', 'Account Name', 'Amount'];
            case 'trial_balance':
                return ['Account Code', 'Account Name', 'Account Side', 'Debit', 'Credit', 'Balance'];
            case 'journal_entries':
                return ['Category', 'Field 1', 'Field 2', 'Field 3', 'Field 4', 'Field 5', 'Field 6', 'Field 7'];
            case 'account_ledger':
                return ['Category', 'Field 1', 'Field 2', 'Field 3', 'Field 4', 'Field 5', 'Field 6', 'Field 7', 'Field 8'];
            default:
                return ['Data'];
        }
    }

    private function getSheetTitle(): string
    {
        $titles = [
            'general_ledger' => 'General Ledger',
            'balance_sheet' => 'Balance Sheet',
            'income_statement' => 'Income Statement',
            'trial_balance' => 'Trial Balance',
            'journal_entries' => 'Journal Entries',
            'account_ledger' => 'Account Ledger'
        ];

        return $titles[$this->reportResult->reportKey] ?? 'Financial Report';
    }
}