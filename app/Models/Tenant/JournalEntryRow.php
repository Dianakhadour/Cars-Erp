<?php 
namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Enums\EntryType;

class JournalEntryRow extends Model
{
    use SoftDeletes;
    
    protected $connection = 'tenant';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $table = 'journal_entry_rows';
    
    protected $fillable = [
        'journal_entry_id',
        'account_id',
        'company_id',
        'branch_id',
        'line_number',
        'debit',
        'credit',
        'currency_amount',
        'issue_date',
        'finance_year',
        'entry_type',
        'cost_center_id',
        'project_id',
        'reference',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'debit' => 'decimal:4',
        'credit' => 'decimal:4',
        'currency_amount' => 'decimal:4',
        'entry_type' => EntryType::class,
    ];

    protected static function booted()
    {
        static::creating(function ($row) {
            if (empty($row->{$row->getKeyName()})) {
                $row->{$row->getKeyName()} = (string) \Illuminate\Support\Str::uuid();
            }
            
            // Auto-set finance year from issue date
            if (!$row->finance_year && $row->issue_date) {
                $row->finance_year = $row->issue_date->year;
            }
        });
    }

    // Relationships
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(COAAccount::class, 'account_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    // Scopes
    public function scopeDebitEntries($query)
    {
        return $query->where('debit', '>', 0);
    }

    public function scopeCreditEntries($query)
    {
        return $query->where('credit', '>', 0);
    }

    public function scopeByAccount($query, $accountId)
    {
        return $query->where('account_id', $accountId);
    }

    // Business logic methods
    public function getAmount(): float
    {
        return $this->debit > 0 ? $this->debit : $this->credit;
    }

    public function isDebit(): bool
    {
        return $this->debit > 0;
    }

    public function isCredit(): bool
    {
        return $this->credit > 0;
    }

    public function isValid(): bool
    {
        // Row must have either debit or credit, not both, not zero
        return ($this->debit > 0 && $this->credit == 0) || 
               ($this->credit > 0 && $this->debit == 0);
    }
}