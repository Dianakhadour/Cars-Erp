<?php
 

namespace App\Models\Tenant;

use App\Models\User;
use App\Enums\JournalStatus;
use App\Enums\JournalEntryBy;
use App\Enums\JournalEntryType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalEntry extends Model
{
    use SoftDeletes;
    
    protected $connection = 'tenant';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $table = 'journal_entries';
    
    protected $fillable = [
        'company_id',
        'branch_id',
        'journal_entry_number',
        'issue_date',
        'posting_date',
        'journal_entry_type',
        'journal_entry_by',
        'document_id',
        'document_ref',
        'narration',
        'status',
        'finance_year',
        'total_debit',
        'total_credit',
        'currency',
        'exchange_rate',
        'created_by',
        'posted_by',
        'posted_at',
        'attachments',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'posting_date' => 'date',
        'posted_at' => 'datetime',
        'total_debit' => 'decimal:4',
        'total_credit' => 'decimal:4',
        'exchange_rate' => 'decimal:6',
        'attachments' => 'array',
        'journal_entry_type' => JournalEntryType::class,
        'journal_entry_by' => JournalEntryBy::class,
        'status' => JournalStatus::class,
    ];

    protected static function booted()
    {
        static::creating(function ($entry) {
            if (empty($entry->{$entry->getKeyName()})) {
                $entry->{$entry->getKeyName()} = (string) \Illuminate\Support\Str::uuid();
            }
            
            // Auto-set finance year from posting date
            if (!$entry->finance_year && $entry->posting_date) {
                $entry->finance_year = $entry->posting_date->year;
            }
        });
    }

    // Relationships
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function rows(): HasMany
    {
        return $this->hasMany(JournalEntryRow::class, 'journal_entry_id');
    }

    // Scopes
    public function scopeDraft($query)
    {
        return $query->where('status', JournalStatus::DRAFT);
    }

    public function scopePosted($query)
    {
        return $query->where('status', JournalStatus::POSTED);
    }

    public function scopeByDateRange($query, $from, $to)
    {
        return $query->whereBetween('issue_date', [$from, $to]);
    }

    public function scopeByBranch($query, $branchId = null)
    {
        if ($branchId) {
            return $query->where('branch_id', $branchId);
        }
        return $query->whereNull('branch_id');
    }

    public function scopeByType($query, $type)
    {
        return $query->where('journal_entry_type', $type);
    }

    public function scopeByFinanceYear($query, $year)
    {
        return $query->where('finance_year', $year);
    }

    // Business logic methods
    public function isBalanced(): bool
    {
        return $this->total_debit === $this->total_credit;
    }

    public function canBePosted(): bool
    {
        return $this->status === JournalStatus::DRAFT && $this->isBalanced();
    }

    public function canBeReversed(): bool
    {
        return $this->status === JournalStatus::POSTED;
    }

    public function getDebitCreditDifference(): float
    {
        return abs($this->total_debit - $this->total_credit);
    }
    
    /**
     * Get only posted journal entry rows
     */
    public function postedRows(): HasMany
    {
        return $this->hasMany(JournalEntryRow::class, 'journal_entry_id')
            ->whereHas('journalEntry', function($query) {
                $query->where('status', 'posted');
            });
    }

    /**
     * Get journal entry rows with their accounts
     */
    public function rowsWithAccounts(): HasMany
    {
        return $this->hasMany(JournalEntryRow::class, 'journal_entry_id')
            ->with('account');
    }
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    public function postedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }
}