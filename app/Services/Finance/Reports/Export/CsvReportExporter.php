<?php

namespace App\Services\Finance\Reports\Export;

use App\DTO\Finance\ReportResult;

class CsvReportExporter implements ReportExporter
{
    public function export(ReportResult $result, string $format): ReportExportHandle
    {
        $filename = $this->generateFilename($result->reportKey, $format);
        $csvContent = $this->convertToCsv($result);
        
        return new ReportExportHandle(
            filename: $filename,
            contentType: 'text/csv',
            content: $csvContent,
            format: $format
        );
    }

    private function convertToCsv(ReportResult $result): string
    {
        $output = fopen('php://temp', 'r+');
        
        // Add header
        fputcsv($output, [$result->title]);
        fputcsv($output, []); // Empty line
        
        // Convert data based on report type
        switch ($result->reportKey) {
            case 'trial_balance':
                $this->exportTrialBalance($result, $output);
                break;
            case 'general_ledger':
                $this->exportGeneralLedger($result, $output);
                break;
            case 'balance_sheet':
                $this->exportBalanceSheet($result, $output);
                break;
            case 'income_statement':
                $this->exportIncomeStatement($result, $output);
                break;
            case 'journal_entries':
                $this->exportJournalEntries($result, $output);
                break;
            case 'account_ledger':
                $this->exportAccountLedger($result, $output);
                break;
            default:
                $this->exportGeneric($result, $output);
        }
        
        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);
        
        return $csvContent;
    }

    private function exportGeneralLedger(ReportResult $result, $output): void
    {
        // Header
        fputcsv($output, [
            'Date', 'Journal Entry', 'Account Code', 'Account Name', 
            'Debit', 'Credit', 'Transaction Amount', 'Running Balance', 'Description'
        ]);
        
        // Data
        foreach ($result->data as $row) {
            fputcsv($output, [
                $row['date'],
                $row['journal_entry_number'],
                $row['account_code'],
                $row['account_name'],
                number_format($row['debit'], 2),
                number_format($row['credit'], 2),
                number_format($row['transaction_amount'], 2),
                number_format($row['running_balance'], 2),
                $row['description']
            ]);
        }
        
        // Summary
        fputcsv($output, []);
        fputcsv($output, ['Summary']);
        fputcsv($output, ['Total Debit', number_format($result->metadata['total_debit'] ?? 0, 2)]);
        fputcsv($output, ['Total Credit', number_format($result->metadata['total_credit'] ?? 0, 2)]);
        fputcsv($output, ['Total Transactions', $result->metadata['total_transactions'] ?? 0]);
    }

    private function exportBalanceSheet(ReportResult $result, $output): void
    {
        $data = $result->data;
        
        // Assets
        fputcsv($output, ['ASSETS']);
        fputcsv($output, ['Account Code', 'Account Name', 'Balance']);
        foreach ($data['assets'] as $asset) {
            fputcsv($output, [
                $asset['account_code'],
                $asset['account_name'],
                number_format($asset['balance'], 2)
            ]);
        }
        fputcsv($output, ['Total Assets', '', number_format($data['totals']['total_assets'], 2)]);
        fputcsv($output, []);
        
        // Liabilities
        fputcsv($output, ['LIABILITIES']);
        fputcsv($output, ['Account Code', 'Account Name', 'Balance']);
        foreach ($data['liabilities'] as $liability) {
            fputcsv($output, [
                $liability['account_code'],
                $liability['account_name'],
                number_format($liability['balance'], 2)
            ]);
        }
        fputcsv($output, ['Total Liabilities', '', number_format($data['totals']['total_liabilities'], 2)]);
        fputcsv($output, []);
        
        // Equity
        fputcsv($output, ['EQUITY']);
        fputcsv($output, ['Account Code', 'Account Name', 'Balance']);
        foreach ($data['equity'] as $equity) {
            fputcsv($output, [
                $equity['account_code'],
                $equity['account_name'],
                number_format($equity['balance'], 2)
            ]);
        }
        fputcsv($output, ['Total Equity', '', number_format($data['totals']['total_equity'], 2)]);
        fputcsv($output, []);
        
        // Accounting Equation
        fputcsv($output, ['ACCOUNTING EQUATION']);
        fputcsv($output, ['Assets', number_format($data['accounting_equation']['assets'], 2)]);
        fputcsv($output, ['Liabilities + Equity', number_format($data['accounting_equation']['liabilities_plus_equity'], 2)]);
        fputcsv($output, ['Balanced', $data['accounting_equation']['is_balanced'] ? 'YES' : 'NO']);
    }

    private function exportAccountLedger(ReportResult $result, $output): void
    {
        $summary = $result->metadata['summary'];
        
        // Account Info
        fputcsv($output, ['Account:', $summary['account']['code'] . ' - ' . $summary['account']['name']]);
        fputcsv($output, ['Period:', $summary['period']['from'] . ' to ' . $summary['period']['to']]);
        fputcsv($output, []);
        
        // Header
        fputcsv($output, [
            'Date', 'Journal Entry', 'Type', 'Narration', 'Reference',
            'Debit', 'Credit', 'Amount', 'Running Balance'
        ]);
        
        // Data
        foreach ($result->data as $row) {
            fputcsv($output, [
                $row['date'],
                $row['journal_entry_number'],
                $row['journal_entry_type'],
                $row['narration'],
                $row['reference'],
                number_format($row['debit'], 2),
                number_format($row['credit'], 2),
                number_format($row['transaction_amount'], 2),
                number_format($row['running_balance'], 2)
            ]);
        }
        
        // Summary
        fputcsv($output, []);
        fputcsv($output, ['SUMMARY']);
        fputcsv($output, ['Opening Balance', number_format($summary['balances']['opening_balance'], 2)]);
        fputcsv($output, ['Closing Balance', number_format($summary['balances']['closing_balance'], 2)]);
        fputcsv($output, ['Net Change', number_format($summary['balances']['net_change'], 2)]);
        fputcsv($output, ['Total Debit', number_format($summary['balances']['total_debit'], 2)]);
        fputcsv($output, ['Total Credit', number_format($summary['balances']['total_credit'], 2)]);
    }

    private function exportTrialBalance(ReportResult $result, $output): void
    {
        // Header
        fputcsv($output, ['Account Code', 'Account Name', 'Account Side', 'Debit', 'Credit', 'Balance']);
        
        // Data rows
        foreach ($result->data as $account) {
            fputcsv($output, [
                $account['account_code'],
                $account['account_name'],
                $account['account_side'] instanceof \App\Enums\AccountSide 
                    ? $account['account_side']->value 
                    : (string) $account['account_side'],  
                number_format($account['debit'] ?? 0, 2),
                number_format($account['credit'] ?? 0, 2),
                number_format($account['balance'] ?? 0, 2)
            ]);
        }
        
        // Summary section
        fputcsv($output, []);
        fputcsv($output, ['SUMMARY']);
        fputcsv($output, ['Total Debit', '', '', number_format($result->metadata['total_debit'] ?? 0, 2)]);
        fputcsv($output, ['Total Credit', '', '', number_format($result->metadata['total_credit'] ?? 0, 2)]);
        fputcsv($output, ['Difference', '', '', number_format(($result->metadata['total_debit'] ?? 0) - ($result->metadata['total_credit'] ?? 0), 2)]);
        
        // Balance check
        fputcsv($output, []);
        fputcsv($output, ['BALANCE CHECK']);
        fputcsv($output, ['Status', $result->metadata['is_balanced'] ? 'BALANCED  ' : 'NOT BALANCED  ']);
        fputcsv($output, ['Total Accounts', $result->metadata['total_accounts'] ?? 0]);
        fputcsv($output, ['Asset Accounts', $result->metadata['asset_accounts'] ?? 0]);
        fputcsv($output, ['Liability Accounts', $result->metadata['liability_accounts'] ?? 0]);
        fputcsv($output, ['Equity Accounts', $result->metadata['equity_accounts'] ?? 0]);
        fputcsv($output, ['Income Accounts', $result->metadata['income_accounts'] ?? 0]);
        fputcsv($output, ['Expense Accounts', $result->metadata['expense_accounts'] ?? 0]);
    }

    private function exportIncomeStatement(ReportResult $result, $output): void
    {
        $data = $result->data;
        
        // Revenues section
        fputcsv($output, ['REVENUES']);
        fputcsv($output, ['Account Code', 'Account Name', 'Amount']);
        foreach ($data['revenues'] as $revenue) {
            fputcsv($output, [
                $revenue['account_code'],
                $revenue['account_name'],
                number_format($revenue['balance'], 2)
            ]);
        }
        fputcsv($output, ['Total Revenue', '', number_format($data['totals']['total_revenue'], 2)]);
        fputcsv($output, []);
        
        // Expenses section  
        fputcsv($output, ['EXPENSES']);
        fputcsv($output, ['Account Code', 'Account Name', 'Amount']);
        foreach ($data['expenses'] as $expense) {
            fputcsv($output, [
                $expense['account_code'],
                $expense['account_name'],
                number_format($expense['balance'], 2)
            ]);
        }
        fputcsv($output, ['Total Expenses', '', number_format($data['totals']['total_expenses'], 2)]);
        fputcsv($output, []);
        
        // Net Income calculation
        fputcsv($output, ['NET INCOME CALCULATION']);
        fputcsv($output, ['Total Revenue', number_format($data['totals']['total_revenue'], 2)]);
        fputcsv($output, ['Less: Total Expenses', number_format($data['totals']['total_expenses'], 2)]);
        fputcsv($output, ['Net Income', number_format($data['totals']['net_income'], 2)]);
        fputcsv($output, []);
        
        // Profitability metrics
        fputcsv($output, ['PROFITABILITY METRICS']);
        fputcsv($output, ['Gross Margin', number_format($data['profitability_metrics']['gross_margin'] ?? 0, 2) . '%']);
        fputcsv($output, ['Net Margin', number_format($data['profitability_metrics']['net_margin'] ?? 0, 2) . '%']);
        fputcsv($output, ['Expense Ratio', number_format($data['profitability_metrics']['expense_ratio'] ?? 0, 2) . '%']);
        fputcsv($output, ['Operating Income', number_format($data['totals']['operating_income'] ?? 0, 2)]);
        
        // Period info
        fputcsv($output, []);
        fputcsv($output, ['PERIOD INFORMATION']);
        fputcsv($output, ['Report Period', $result->metadata['period'] ?? '']);
        fputcsv($output, ['Revenue Accounts', $result->metadata['revenue_accounts'] ?? 0]);
        fputcsv($output, ['Expense Accounts', $result->metadata['expense_accounts'] ?? 0]);
        fputcsv($output, ['Total Accounts', $result->metadata['total_accounts'] ?? 0]);
    }

    private function exportJournalEntries(ReportResult $result, $output): void
    {
        $summary = $result->metadata['summary'] ?? [];
        
        // Global summary first
        fputcsv($output, ['REPORT SUMMARY']);
        fputcsv($output, ['Total Journal Entries', $summary['total_entries'] ?? 0]);
        fputcsv($output, ['Posted Entries', $summary['posted_entries'] ?? 0]);
        fputcsv($output, ['Draft Entries', $summary['draft_entries'] ?? 0]);
        fputcsv($output, ['Total Debit Amount', number_format($summary['total_debit'] ?? 0, 2)]);
        fputcsv($output, ['Total Credit Amount', number_format($summary['total_credit'] ?? 0, 2)]);
        fputcsv($output, []);
        
        // Entry types breakdown
        if (isset($summary['entry_types']) && !empty($summary['entry_types'])) {
            fputcsv($output, ['ENTRY TYPES BREAKDOWN']);
            foreach ($summary['entry_types'] as $type => $count) {
                fputcsv($output, [ucfirst(str_replace('_', ' ', $type)), $count]);
            }
            fputcsv($output, []);
        }
        
        // Individual journal entries
        fputcsv($output, ['JOURNAL ENTRIES DETAIL']);
        fputcsv($output, []);
        
        foreach ($result->data as $index => $entry) {
            // Entry header
            fputcsv($output, ['ENTRY #' . ($index + 1)]);
            fputcsv($output, ['Journal Entry Number', $entry['journal_entry_number']]);
            fputcsv($output, ['Issue Date', $entry['issue_date']]);
            fputcsv($output, ['Posting Date', $entry['posting_date']]);
            fputcsv($output, ['Type', $entry['journal_entry_type']]);
            fputcsv($output, ['Created By', $entry['journal_entry_by']]);
            fputcsv($output, ['Status', $entry['status']]);
            fputcsv($output, ['Narration', $entry['narration']]);
            fputcsv($output, ['Document Reference', $entry['document_ref'] ?? 'N/A']);
            fputcsv($output, ['Total Debit', number_format($entry['total_debit'], 2)]);
            fputcsv($output, ['Total Credit', number_format($entry['total_credit'], 2)]);
            fputcsv($output, ['Currency', $entry['currency']]);
            fputcsv($output, ['Created By User', $entry['created_by']]);
            fputcsv($output, ['Posted By User', $entry['posted_by'] ?? 'N/A']);
            fputcsv($output, ['Posted At', $entry['posted_at'] ?? 'N/A']);
            fputcsv($output, []);
            
            // Entry rows
            fputcsv($output, ['LINE ITEMS']);
            fputcsv($output, ['Line', 'Account Code', 'Account Name', 'Account Side', 'Debit', 'Credit', 'Entry Type', 'Reference', 'Issue Date']);
            
            foreach ($entry['rows'] as $row) {
                fputcsv($output, [
                    $row['line_number'],
                    $row['account_code'],
                    $row['account_name'],
                    $row['account_side'],
                    number_format($row['debit'], 2),
                    number_format($row['credit'], 2),
                    $row['entry_type'],
                    $row['reference'],
                    $row['issue_date']
                ]);
            }
            
            // Separator between entries (unless it's the last one)
            if ($index < count($result->data) - 1) {
                fputcsv($output, []);
                fputcsv($output, ['---', '---', '---', '---', '---', '---', '---', '---', '---']);
                fputcsv($output, []);
            }
        }
    }
    private function generateFilename(string $reportKey, string $format): string
    {
        $timestamp = now()->format('Y-m-d_H-i-s');
        return "{$reportKey}_{$timestamp}.{$format}";
    }
}