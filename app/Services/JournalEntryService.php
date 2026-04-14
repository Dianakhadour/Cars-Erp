<?php
 
namespace App\Services;

use App\Models\Tenant\JournalEntry;
use App\Models\Tenant\JournalEntryRow;
//use App\Models\Tenant\User;
use App\Repositories\JournalEntryRepository;
use App\Repositories\JournalEntryRowRepository;
use App\Repositories\COAAccountRepository;
use App\Enums\JournalStatus;
use App\Enums\JournalEntryType;
use App\Enums\JournalEntryBy;
use App\Enums\EntryType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class JournalEntryService
{
    public function __construct(
        private JournalEntryRepository $entryRepository,
        private JournalEntryRowRepository $rowRepository,
        private COAAccountRepository $accountRepository
    ) {}

    public function createDraft(array $data, string $companyId, ?string $branchId,  $user): array
    {
        if (!$user || !method_exists($user, 'getAuthIdentifier')) {
            throw ValidationException::withMessages([
                'user' => 'Valid user object is required to create journal entry.'
            ]);
        }
        if (empty($data['rows']) || !is_array($data['rows'])) {
            throw ValidationException::withMessages([
                'rows' => 'Journal entry must have at least one row.'
            ]);
        }

        return DB::transaction(function () use ($data, $companyId, $branchId, $user) {
            // Generate journal number
            $journalNumber = $this->entryRepository->generateJournalNumber(
                $companyId, 
                $branchId, 
                $data['finance_year']
            );

            // Create journal entry header
            $entry = JournalEntry::create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'journal_entry_number' => $journalNumber,
                'issue_date' => $data['issue_date'],
                'posting_date' => $data['posting_date'],
                'journal_entry_type' => JournalEntryType::from($data['journal_entry_type']),
                'journal_entry_by' => JournalEntryBy::from($data['journal_entry_by']),
                'document_id' => $data['document_id'] ?? null,
                'document_ref' => $data['document_ref'] ?? null,
                'narration' => $data['narration'] ?? null,
                'status' => JournalStatus::DRAFT,
                'finance_year' => $data['finance_year'],
                'currency' => $data['currency'] ?? 'SAR',
                'exchange_rate' => $data['exchange_rate'] ?? 1,
                'created_by' => $user->id,
            ]);

            $totalDebit = 0;
            $totalCredit = 0;
            $lineNumber = 1;

            foreach ($data['rows'] as $rowData) {
                
                $account = $this->accountRepository->findById($rowData['account_id'], $companyId, $branchId);
                if (!$account || !$account->is_active) {
                    throw ValidationException::withMessages([
                        "rows.{$lineNumber}.account_id" => 'Account not found or not active.'
                    ]);
                }

                // Validate row has either debit or credit
                $debit = (float) ($rowData['debit'] ?? 0);
                $credit = (float) ($rowData['credit'] ?? 0);

                if ($debit > 0 && $credit > 0) {
                    throw ValidationException::withMessages([
                        "rows.{$lineNumber}.debit" => 'Row cannot have both debit and credit amounts.'
                    ]);
                }

                if ($debit == 0 && $credit == 0) {
                    throw ValidationException::withMessages([
                        "rows.{$lineNumber}.debit" => 'Row must have either debit or credit amount.'
                    ]);
                }

                // Create journal entry row
                $row = JournalEntryRow::create([
                    'journal_entry_id' => $entry->id,
                    'account_id' => $rowData['account_id'],
                    'company_id' => $companyId,
                    'branch_id' => $branchId,
                    'line_number' => $lineNumber,
                    'debit' => $debit,
                    'credit' => $credit,
                    'currency_amount' => $rowData['currency_amount'] ?? null,
                    'issue_date' => $data['issue_date'],
                    'finance_year' => $data['finance_year'],
                    'entry_type' => EntryType::from($rowData['entry_type'] ?? 'normal'),
                    'reference' => $rowData['reference'] ?? null,
                ]);

                $totalDebit += $debit;
                $totalCredit += $credit;
                $lineNumber++;
            }

            // Validate debit = credit
            if (round($totalDebit, 2) !== round($totalCredit, 2)) {
                throw ValidationException::withMessages([
                    'rows' => "Debit and credit totals must be equal. Debit: {$totalDebit}, Credit: {$totalCredit}"
                ]);
            }

            // Update entry with totals
            $entry->update([
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit
            ]);

            return $this->formatJournalEntryResponse($entry->fresh(['rows.account']));
        });
    }

    public function postEntry(string $id, string $companyId, ?string $branchId,   $user): array
    {
        $entry = $this->entryRepository->findById($id, $companyId, $branchId);
        
        if (!$entry) {
            throw ValidationException::withMessages([
                'entry' => 'Journal entry not found.'
            ]);
        }

        if (!$entry->canBePosted()) {
            throw ValidationException::withMessages([
                'entry' => 'Only balanced draft entries can be posted.'
            ]);
        }

        // Additional validation: Check all accounts are active
        foreach ($entry->rows as $row) {
            if (!$row->account->is_active) {
                throw ValidationException::withMessages([
                    'entry' => "Cannot post entry: Account {$row->account->code} - {$row->account->name} is not active."
                ]);
            }
        }

        return DB::transaction(function () use ($entry, $user) {
            $entry->update([
                'status' => JournalStatus::POSTED,
                'posted_by' => $user->id,
                'posted_at' => now(),
            ]);

            return $this->formatJournalEntryResponse($entry->fresh(['rows.account', 'poster']));
        });
    }

    public function reverseEntry(string $id, string $companyId, ?string $branchId,   $user): array
    {
        $originalEntry = $this->entryRepository->findById($id, $companyId, $branchId);
        
        if (!$originalEntry) {
            throw ValidationException::withMessages([
                'entry' => 'Journal entry not found.'
            ]);
        }

        if (!$originalEntry->canBeReversed()) {
            throw ValidationException::withMessages([
                'entry' => 'Only posted entries can be reversed.'
            ]);
        }

        return DB::transaction(function () use ($originalEntry, $companyId, $branchId, $user) {
            // Generate reversal journal number
            $reversalNumber = $originalEntry->journal_entry_number . '-R';

            // Create reversal entry
            $reversalEntry = JournalEntry::create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'journal_entry_number' => $reversalNumber,
                'issue_date' => now()->format('Y-m-d'),
                'posting_date' => now()->format('Y-m-d'),
                'journal_entry_type' => $originalEntry->journal_entry_type,
                'journal_entry_by' => JournalEntryBy::AUTOMATIC,
                'document_id' => $originalEntry->id,
                'document_ref' => 'Reversal of ' . $originalEntry->journal_entry_number,
                'narration' => 'Reversal of journal entry: ' . $originalEntry->journal_entry_number,
                'status' => JournalStatus::POSTED,
                'finance_year' => now()->year,
                'currency' => $originalEntry->currency,
                'exchange_rate' => $originalEntry->exchange_rate,
                'created_by' => $user->id,
                'posted_by' => $user->id,
                'posted_at' => now(),
            ]);

            $lineNumber = 1;
            $totalDebit = 0;
            $totalCredit = 0;

            foreach ($originalEntry->rows as $originalRow) {
                // Reverse debit/credit
                $debit = $originalRow->credit;
                $credit = $originalRow->debit;

                JournalEntryRow::create([
                    'journal_entry_id' => $reversalEntry->id,
                    'account_id' => $originalRow->account_id,
                    'company_id' => $companyId,
                    'branch_id' => $branchId,
                    'line_number' => $lineNumber,
                    'debit' => $debit,
                    'credit' => $credit,
                    'currency_amount' => $originalRow->currency_amount,
                    'issue_date' => now()->format('Y-m-d'),
                    'finance_year' => now()->year,
                    'entry_type' => EntryType::REVERSAL,
                    'reference' => 'Reversal of ' . $originalEntry->journal_entry_number . ' line ' . $originalRow->line_number,
                ]);

                $totalDebit += $debit;
                $totalCredit += $credit;
                $lineNumber++;
            }

            $reversalEntry->update([
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit
            ]);

            return $this->formatJournalEntryResponse($reversalEntry->fresh(['rows.account']));
        });
    }

    public function listEntries(
        ?string $from = null,
        ?string $to = null,
        ?string $branchId = null,
        ?string $companyId = null,
        ?string $status = null,
        ?string $type = null
    ): array {
        $statusEnum = $status ? JournalStatus::tryFrom($status) : null;
        
        $entries = $this->entryRepository->listFiltered($from, $to, $branchId, $companyId, $statusEnum, $type);
        
        return $entries->map(function (JournalEntry $entry) {
            return $this->formatJournalEntryListResponse($entry);
        })->toArray();
    }

    public function getEntryWithRows(string $id, string $companyId, ?string $branchId = null): array
    {
        $entry = $this->entryRepository->findById($id, $companyId, $branchId);
        
        if (!$entry) {
            throw ValidationException::withMessages([
                'entry' => 'Journal entry not found.'
            ]);
        }

        $rows = $this->rowRepository->getRowsByJournalEntry($id);

        return [
            'entry' => $this->formatJournalEntryResponse($entry),
            'rows' => $rows->map(function (JournalEntryRow $row) {
                return $this->formatJournalEntryRowResponse($row);
            })->toArray()
        ];
    }

    private function formatJournalEntryResponse(JournalEntry $entry): array
    {
        return [
            'id' => $entry->id,
            'journal_entry_number' => $entry->journal_entry_number,
            'issue_date' => $entry->issue_date->format('Y-m-d'),
            'posting_date' => $entry->posting_date->format('Y-m-d'),
            'journal_entry_type' => $entry->journal_entry_type->value,
            'journal_entry_type_label' => $entry->journal_entry_type->label(),
            'journal_entry_by' => $entry->journal_entry_by->value,
            'journal_entry_by_label' => $entry->journal_entry_by->label(),
            'document_id' => $entry->document_id,
            'document_ref' => $entry->document_ref,
            'narration' => $entry->narration,
            'status' => $entry->status->value,
            'status_label' => $entry->status->label(),
            'finance_year' => $entry->finance_year,
            'total_debit' => (float) $entry->total_debit,
            'total_credit' => (float) $entry->total_credit,
            'currency' => $entry->currency,
            'exchange_rate' => (float) $entry->exchange_rate,
            'branch' => $entry->branch ? [
                'id' => $entry->branch->id,
                'name' => $entry->branch->name,
                'code' => $entry->branch->code,
            ] : null,
            'creator' => $entry->creator ? [
                'id' => $entry->creator->id,
                'name' => $entry->creator->name,
            ] : null,
            'poster' => $entry->poster ? [
                'id' => $entry->poster->id,
                'name' => $entry->poster->name,
            ] : null,
            'posted_at' => $entry->posted_at?->format('Y-m-d H:i:s'),
            'attachments' => $entry->attachments,
            'created_at' => $entry->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $entry->updated_at->format('Y-m-d H:i:s'),
        ];
    }

    private function formatJournalEntryListResponse(JournalEntry $entry): array
    {
        return [
            'id' => $entry->id,
            'journal_entry_number' => $entry->journal_entry_number,
            'issue_date' => $entry->issue_date->format('Y-m-d'),
            'journal_entry_type' => $entry->journal_entry_type->value,
            'journal_entry_type_label' => $entry->journal_entry_type->label(),
            'status' => $entry->status->value,
            'status_label' => $entry->status->label(),
            'total_debit' => (float) $entry->total_debit,
            'total_credit' => (float) $entry->total_credit,
            'narration' => $entry->narration,
            'branch' => $entry->branch ? $entry->branch->name : 'Global',
            'creator' => $entry->creator?->name,
        ];
    }

    private function formatJournalEntryRowResponse(JournalEntryRow $row): array
    {
        return [
            'id' => $row->id,
            'line_number' => $row->line_number,
            'account' => [
                'id' => $row->account->id,
                'code' => $row->account->code,
                'name' => $row->account->name,
                'account_side' => $row->account->account_side->value,
            ],
            'debit' => (float) $row->debit,
            'credit' => (float) $row->credit,
            'entry_type' => $row->entry_type->value,
            'entry_type_label' => $row->entry_type->label(),
            'reference' => $row->reference,
        ];
    }
}