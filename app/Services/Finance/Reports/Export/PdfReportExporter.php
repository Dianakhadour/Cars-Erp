<?php

namespace App\Services\Finance\Reports\Export;

use App\DTO\Finance\ReportResult;
use Dompdf\Dompdf;
use Dompdf\Options;

class PdfReportExporter implements ReportExporter
{
    public function export(ReportResult $result, string $format): ReportExportHandle
    {
        $filename = $this->generateFilename($result->reportKey, $format);
        $htmlContent = $this->convertToHtml($result);
        $pdfContent = $this->generatePdf($htmlContent);
        
        return new ReportExportHandle(
            filename: $filename,
            contentType: 'application/pdf',
            content: $pdfContent,
            format: $format
        );
    }

    private function convertToHtml(ReportResult $result): string
    {
       
        return "
        <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; }
                    .header { text-align: center; margin-bottom: 20px; }
                    .table { width: 100%; border-collapse: collapse; }
                    .table th, .table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                    .table th { background-color: #f2f2f2; }
                    .summary { margin-top: 20px; padding: 15px; background-color: #f9f9f9; }
                </style>
            </head>
            <body>
                <div class='header'>
                    <h1>{$result->title}</h1>
                    <p>Generated on: " . now()->format('Y-m-d H:i:s') . "</p>
                </div>
                " . $this->generateReportHtml($result) . "
            </body>
        </html>
        ";
    }

    private function generateReportHtml(ReportResult $result): string
    {
        switch ($result->reportKey) {
            case 'trial_balance':
                return $this->generateTrialBalanceHtml($result);
            case 'general_ledger':
                return $this->generateGeneralLedgerHtml($result);
            case 'balance_sheet':
                return $this->generateBalanceSheetHtml($result);
            case 'income_statement':
                return $this->generateIncomeStatementHtml($result);
            case 'journal_entries':
                return $this->generateJournalEntriesHtml($result);
            case 'account_ledger':
                return $this->generateAccountLedgerHtml($result);
            default:
                return $this->generateGenericHtml($result);
        }
    }
    private function generateTrialBalanceHtml(ReportResult $result): string
    {
        $html = '
        <div class="section">
            <h2>Trial Balance</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>Account Code</th>
                        <th>Account Name</th>
                        <th>Side</th>
                        <th>Debit</th>
                        <th>Credit</th>
                        <th>Balance</th>
                    </tr>
                </thead>
                <tbody>';
    
        foreach ($result->data as $account) {
            $accountSide = $account['account_side'] instanceof \App\Enums\AccountSide 
                ? $account['account_side']->value 
                : (string) $account['account_side'];  
            
            $html .= '
                    <tr>
                        <td>' . htmlspecialchars($account['account_code']) . '</td>
                        <td>' . htmlspecialchars($account['account_name']) . '</td>
                        <td>' . htmlspecialchars($accountSide) . '</td>
                        <td class="number">' . number_format($account['debit'] ?? 0, 2) . '</td>
                        <td class="number">' . number_format($account['credit'] ?? 0, 2) . '</td>
                        <td class="number">' . number_format($account['balance'] ?? 0, 2) . '</td>
                    </tr>';
        }
    
        $html .= '
                </tbody>
            </table>
        </div>
    
        <div class="summary">
            <h3>Summary</h3>
            <table class="summary-table">
                <tr>
                    <td><strong>Total Debit:</strong></td>
                    <td class="number">' . number_format($result->metadata['total_debit'] ?? 0, 2) . '</td>
                </tr>
                <tr>
                    <td><strong>Total Credit:</strong></td>
                    <td class="number">' . number_format($result->metadata['total_credit'] ?? 0, 2) . '</td>
                </tr>
                <tr>
                    <td><strong>Difference:</strong></td>
                    <td class="number">' . number_format(($result->metadata['total_debit'] ?? 0) - ($result->metadata['total_credit'] ?? 0), 2) . '</td>
                </tr>
                <tr>
                    <td><strong>Status:</strong></td>
                    <td><span class="' . ($result->metadata['is_balanced'] ? 'status-success' : 'status-error') . '">' . 
                        ($result->metadata['is_balanced'] ? 'BALANCED ✅' : 'NOT BALANCED ❌') . '</span></td>
                </tr>
            </table>
        </div>';
    
        return $html;
    }
    private function generateGeneralLedgerHtml(ReportResult $result): string
    {
        $html = '
        <div class="section">
            <h2>General Ledger</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Journal Entry</th>
                        <th>Account Code</th>
                        <th>Account Name</th>
                        <th>Debit</th>
                        <th>Credit</th>
                        <th>Amount</th>
                        <th>Running Balance</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>';
    
        foreach ($result->data as $row) {
            $html .= '
                    <tr>
                        <td>' . htmlspecialchars($row['date']) . '</td>
                        <td>' . htmlspecialchars($row['journal_entry_number']) . '</td>
                        <td>' . htmlspecialchars($row['account_code']) . '</td>
                        <td>' . htmlspecialchars($row['account_name']) . '</td>
                        <td class="number">' . number_format($row['debit'], 2) . '</td>
                        <td class="number">' . number_format($row['credit'], 2) . '</td>
                        <td class="number">' . number_format($row['transaction_amount'], 2) . '</td>
                        <td class="number">' . number_format($row['running_balance'], 2) . '</td>
                        <td>' . htmlspecialchars($row['description']) . '</td>
                    </tr>';
        }
    
        $html .= '
                </tbody>
            </table>
        </div>
    
        <div class="summary">
            <h3>Summary</h3>
            <table class="summary-table">
                <tr>
                    <td><strong>Total Debit:</strong></td>
                    <td class="number">' . number_format($result->metadata['total_debit'] ?? 0, 2) . '</td>
                </tr>
                <tr>
                    <td><strong>Total Credit:</strong></td>
                    <td class="number">' . number_format($result->metadata['total_credit'] ?? 0, 2) . '</td>
                </tr>
                <tr>
                    <td><strong>Total Transactions:</strong></td>
                    <td>' . ($result->metadata['total_transactions'] ?? 0) . '</td>
                </tr>
                <tr>
                    <td><strong>Total Accounts:</strong></td>
                    <td>' . ($result->metadata['total_accounts'] ?? 0) . '</td>
                </tr>
            </table>
        </div>';
    
        return $html;
    }
  private function generateBalanceSheetHtml(ReportResult $result): string
    {
        $data = $result->data;
        
        $html = '
        <div class="section">
            <h2>Balance Sheet</h2>
            
            <div class="subsection">
                <h3>ASSETS</h3>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Account Code</th>
                            <th>Account Name</th>
                            <th>Balance</th>
                        </tr>
                    </thead>
                    <tbody>';

        foreach ($data['assets'] as $asset) {
            $html .= '
                        <tr>
                            <td>' . htmlspecialchars($asset['account_code']) . '</td>
                            <td>' . htmlspecialchars($asset['account_name']) . '</td>
                            <td class="number">' . number_format($asset['balance'], 2) . '</td>
                        </tr>';
        }

        $html .= '
                        <tr class="total-row">
                            <td colspan="2"><strong>Total Assets</strong></td>
                            <td class="number"><strong>' . number_format($data['totals']['total_assets'], 2) . '</strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="subsection">
                <h3>LIABILITIES</h3>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Account Code</th>
                            <th>Account Name</th>
                            <th>Balance</th>
                        </tr>
                    </thead>
                    <tbody>';

        foreach ($data['liabilities'] as $liability) {
            $html .= '
                        <tr>
                            <td>' . htmlspecialchars($liability['account_code']) . '</td>
                            <td>' . htmlspecialchars($liability['account_name']) . '</td>
                            <td class="number">' . number_format($liability['balance'], 2) . '</td>
                        </tr>';
        }

        $html .= '
                        <tr class="total-row">
                            <td colspan="2"><strong>Total Liabilities</strong></td>
                            <td class="number"><strong>' . number_format($data['totals']['total_liabilities'], 2) . '</strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="subsection">
                <h3>EQUITY</h3>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Account Code</th>
                            <th>Account Name</th>
                            <th>Balance</th>
                        </tr>
                    </thead>
                    <tbody>';

        foreach ($data['equity'] as $equity) {
            $html .= '
                        <tr>
                            <td>' . htmlspecialchars($equity['account_code']) . '</td>
                            <td>' . htmlspecialchars($equity['account_name']) . '</td>
                            <td class="number">' . number_format($equity['balance'], 2) . '</td>
                        </tr>';
        }

        $html .= '
                        <tr class="total-row">
                            <td colspan="2"><strong>Total Equity</strong></td>
                            <td class="number"><strong>' . number_format($data['totals']['total_equity'], 2) . '</strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="accounting-equation">
                <h3>ACCOUNTING EQUATION</h3>
                <table class="equation-table">
                    <tr>
                        <td><strong>Assets:</strong></td>
                        <td class="number">' . number_format($data['accounting_equation']['assets'], 2) . '</td>
                    </tr>
                    <tr>
                        <td><strong>Liabilities + Equity:</strong></td>
                        <td class="number">' . number_format($data['accounting_equation']['liabilities_plus_equity'], 2) . '</td>
                    </tr>
                    <tr>
                        <td><strong>Status:</strong></td>
                        <td><span class="' . ($data['accounting_equation']['is_balanced'] ? 'status-success' : 'status-error') . '">' . 
                            ($data['accounting_equation']['is_balanced'] ? 'BALANCED ✅' : 'NOT BALANCED ❌') . '</span></td>
                    </tr>
                </table>
            </div>
        </div>';

        return $html;
    }
  private function generateIncomeStatementHtml(ReportResult $result): string
    {
        $data = $result->data;
        
        $html = '
        <div class="section">
            <h2>Income Statement (Profit & Loss)</h2>
            
            <div class="subsection">
                <h3>REVENUES</h3>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Account Code</th>
                            <th>Account Name</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>';

        foreach ($data['revenues'] as $revenue) {
            $html .= '
                        <tr>
                            <td>' . htmlspecialchars($revenue['account_code']) . '</td>
                            <td>' . htmlspecialchars($revenue['account_name']) . '</td>
                            <td class="number">' . number_format($revenue['balance'], 2) . '</td>
                        </tr>';
        }

        $html .= '
                        <tr class="total-row">
                            <td colspan="2"><strong>Total Revenue</strong></td>
                            <td class="number"><strong>' . number_format($data['totals']['total_revenue'], 2) . '</strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="subsection">
                <h3>EXPENSES</h3>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Account Code</th>
                            <th>Account Name</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>';

        foreach ($data['expenses'] as $expense) {
            $html .= '
                        <tr>
                            <td>' . htmlspecialchars($expense['account_code']) . '</td>
                            <td>' . htmlspecialchars($expense['account_name']) . '</td>
                            <td class="number">' . number_format($expense['balance'], 2) . '</td>
                        </tr>';
        }

        $html .= '
                        <tr class="total-row">
                            <td colspan="2"><strong>Total Expenses</strong></td>
                            <td class="number"><strong>' . number_format($data['totals']['total_expenses'], 2) . '</strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="net-income">
                <h3>NET INCOME CALCULATION</h3>
                <table class="calculation-table">
                    <tr>
                        <td><strong>Total Revenue:</strong></td>
                        <td class="number">' . number_format($data['totals']['total_revenue'], 2) . '</td>
                    </tr>
                    <tr>
                        <td><strong>Less: Total Expenses:</strong></td>
                        <td class="number">' . number_format($data['totals']['total_expenses'], 2) . '</td>
                    </tr>
                    <tr class="net-income-row">
                        <td><strong>NET INCOME:</strong></td>
                        <td class="number"><strong>' . number_format($data['totals']['net_income'], 2) . '</strong></td>
                    </tr>
                </table>
            </div>

            <div class="metrics">
                <h3>PROFITABILITY METRICS</h3>
                <table class="metrics-table">
                    <tr>
                        <td><strong>Net Margin:</strong></td>
                        <td class="number">' . number_format($data['profitability_metrics']['net_margin'] ?? 0, 2) . '%</td>
                    </tr>
                    <tr>
                        <td><strong>Expense Ratio:</strong></td>
                        <td class="number">' . number_format($data['profitability_metrics']['expense_ratio'] ?? 0, 2) . '%</td>
                    </tr>
                    <tr>
                        <td><strong>Gross Profit:</strong></td>
                        <td class="number">' . number_format($data['totals']['gross_profit'] ?? 0, 2) . '</td>
                    </tr>
                </table>
            </div>
        </div>';

        return $html;
    }
  
    private function generateJournalEntriesHtml(ReportResult $result): string
    {
        $summary = $result->metadata['summary'] ?? [];
        
        $html = '
        <div class="section">
            <h2>Journal Entries Report</h2>
            
            <div class="summary">
                <h3>Report Summary</h3>
                <table class="summary-table">
                    <tr>
                        <td><strong>Total Journal Entries:</strong></td>
                        <td>' . ($summary['total_entries'] ?? 0) . '</td>
                    </tr>
                    <tr>
                        <td><strong>Posted Entries:</strong></td>
                        <td>' . ($summary['posted_entries'] ?? 0) . '</td>
                    </tr>
                    <tr>
                        <td><strong>Draft Entries:</strong></td>
                        <td>' . ($summary['draft_entries'] ?? 0) . '</td>
                    </tr>
                    <tr>
                        <td><strong>Total Debit:</strong></td>
                        <td class="number">' . number_format($summary['total_debit'] ?? 0, 2) . '</td>
                    </tr>
                    <tr>
                        <td><strong>Total Credit:</strong></td>
                        <td class="number">' . number_format($summary['total_credit'] ?? 0, 2) . '</td>
                    </tr>
                </table>
            </div>';
    
        // Individual journal entries
        foreach ($result->data as $index => $entry) {
            
            $entryType = $entry['journal_entry_type'] instanceof \App\Enums\JournalEntryType 
                ? $entry['journal_entry_type']->value 
                : (string) $entry['journal_entry_type'];
                
            $entryBy = $entry['journal_entry_by'] instanceof \App\Enums\JournalEntryBy 
                ? $entry['journal_entry_by']->value 
                : (string) $entry['journal_entry_by'];
                
            $status = $entry['status'] instanceof \App\Enums\JournalStatus 
                ? $entry['status']->value 
                : (string) $entry['status'];
            
            $html .= '
            <div class="journal-entry">
                <h3>Entry #' . ($index + 1) . ' - ' . htmlspecialchars($entry['journal_entry_number']) . '</h3>
                
                <table class="entry-header">
                    <tr>
                        <td><strong>Issue Date:</strong></td>
                        <td>' . htmlspecialchars($entry['issue_date']) . '</td>
                        <td><strong>Type:</strong></td>
                        <td>' . htmlspecialchars($entryType) . '</td>
                    </tr>
                    <tr>
                        <td><strong>Status:</strong></td>
                        <td><span class="status-' . $status . '">' . htmlspecialchars($status) . '</span></td>
                        <td><strong>Created By:</strong></td>
                        <td>' . htmlspecialchars($entryBy) . '</td>
                    </tr>
                    <tr>
                        <td><strong>Narration:</strong></td>
                        <td colspan="3">' . htmlspecialchars($entry['narration']) . '</td>
                    </tr>
                    <tr>
                        <td><strong>Total Debit:</strong></td>
                        <td class="number">' . number_format($entry['total_debit'], 2) . '</td>
                        <td><strong>Total Credit:</strong></td>
                        <td class="number">' . number_format($entry['total_credit'], 2) . '</td>
                    </tr>
                </table>
    
                <h4>Line Items</h4>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Line</th>
                            <th>Account Code</th>
                            <th>Account Name</th>
                            <th>Debit</th>
                            <th>Credit</th>
                            <th>Reference</th>
                        </tr>
                    </thead>
                    <tbody>';
    
            foreach ($entry['rows'] as $row) {
                $html .= '
                        <tr>
                            <td>' . $row['line_number'] . '</td>
                            <td>' . htmlspecialchars($row['account_code']) . '</td>
                            <td>' . htmlspecialchars($row['account_name']) . '</td>
                            <td class="number">' . number_format($row['debit'], 2) . '</td>
                            <td class="number">' . number_format($row['credit'], 2) . '</td>
                            <td>' . htmlspecialchars($row['reference']) . '</td>
                        </tr>';
            }
    
            $html .= '
                    </tbody>
                </table>
            </div>';
            
            // Add page break except for last entry
            if ($index < count($result->data) - 1) {
                $html .= '<div style="page-break-after: always;"></div>';
            }
        }
    
        $html .= '</div>';
    
        return $html;
    }

    private function generateAccountLedgerHtml(ReportResult $result): string
    {
        $summary = $result->metadata['summary'];
        
         
        $accountSide = $summary['account']['account_side'] instanceof \App\Enums\AccountSide 
            ? $summary['account']['account_side']->value 
            : (string) $summary['account']['account_side'];
            
        $reportType = $summary['account']['report_type'] instanceof \App\Enums\ReportType 
            ? $summary['account']['report_type']->value 
            : (string) $summary['account']['report_type'];
        
        $html = '
        <div class="section">
            <h2>Account Ledger - ' . htmlspecialchars($summary['account']['code']) . ' - ' . htmlspecialchars($summary['account']['name']) . '</h2>
            
            <div class="account-info">
                <table class="info-table">
                    <tr>
                        <td><strong>Account Code:</strong></td>
                        <td>' . htmlspecialchars($summary['account']['code']) . '</td>
                        <td><strong>Account Side:</strong></td>
                        <td>' . htmlspecialchars($accountSide) . '</td>
                    </tr>
                    <tr>
                        <td><strong>Account Type:</strong></td>
                        <td>' . htmlspecialchars($summary['account']['type']) . '</td>
                        <td><strong>Report Type:</strong></td>
                        <td>' . htmlspecialchars($reportType) . '</td>
                    </tr>
                    <tr>
                        <td><strong>Period:</strong></td>
                        <td colspan="3">' . htmlspecialchars($summary['period']['from']) . ' to ' . htmlspecialchars($summary['period']['to']) . '</td>
                    </tr>
                </table>
            </div>
    
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Journal Entry</th>
                        <th>Type</th>
                        <th>Narration</th>
                        <th>Reference</th>
                        <th>Debit</th>
                        <th>Credit</th>
                        <th>Amount</th>
                        <th>Running Balance</th>
                    </tr>
                </thead>
                <tbody>';
    
        foreach ($result->data as $row) {
            
            $entryType = $row['journal_entry_type'] instanceof \App\Enums\JournalEntryType 
                ? $row['journal_entry_type']->value 
                : (string) $row['journal_entry_type'];
                
            $html .= '
                    <tr>
                        <td>' . htmlspecialchars($row['date']) . '</td>
                        <td>' . htmlspecialchars($row['journal_entry_number']) . '</td>
                        <td>' . htmlspecialchars($entryType) . '</td>
                        <td>' . htmlspecialchars($row['narration']) . '</td>
                        <td>' . htmlspecialchars($row['reference']) . '</td>
                        <td class="number">' . number_format($row['debit'], 2) . '</td>
                        <td class="number">' . number_format($row['credit'], 2) . '</td>
                        <td class="number">' . number_format($row['transaction_amount'], 2) . '</td>
                        <td class="number">' . number_format($row['running_balance'], 2) . '</td>
                    </tr>';
        }
    
        $html .= '
                </tbody>
            </table>
    
            <div class="summary">
                <h3>Account Summary</h3>
                <table class="summary-table">
                    <tr>
                        <td><strong>Opening Balance:</strong></td>
                        <td class="number">' . number_format($summary['balances']['opening_balance'], 2) . '</td>
                    </tr>
                    <tr>
                        <td><strong>Closing Balance:</strong></td>
                        <td class="number">' . number_format($summary['balances']['closing_balance'], 2) . '</td>
                    </tr>
                    <tr>
                        <td><strong>Net Change:</strong></td>
                        <td class="number">' . number_format($summary['balances']['net_change'], 2) . '</td>
                    </tr>
                    <tr>
                        <td><strong>Total Debit:</strong></td>
                        <td class="number">' . number_format($summary['balances']['total_debit'], 2) . '</td>
                    </tr>
                    <tr>
                        <td><strong>Total Credit:</strong></td>
                        <td class="number">' . number_format($summary['balances']['total_credit'], 2) . '</td>
                    </tr>
                    <tr>
                        <td><strong>Total Transactions:</strong></td>
                        <td>' . $summary['activity']['total_transactions'] . '</td>
                    </tr>
                    <tr>
                        <td><strong>Debit Transactions:</strong></td>
                        <td>' . $summary['activity']['debit_transactions'] . '</td>
                    </tr>
                    <tr>
                        <td><strong>Credit Transactions:</strong></td>
                        <td>' . $summary['activity']['credit_transactions'] . '</td>
                    </tr>
                </table>
            </div>
        </div>';
    
        return $html;
    }

    private function generatePdf(string $html): string
    {
        $options = new Options();
        $options->set('defaultFont', 'Arial');
        
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        
        return $dompdf->output();
    }

    private function generateFilename(string $reportKey, string $format): string
    {
        $timestamp = now()->format('Y-m-d_H-i-s');
        return "{$reportKey}_{$timestamp}.{$format}";
    }
}