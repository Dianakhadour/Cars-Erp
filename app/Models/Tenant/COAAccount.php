<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Enums\AccountSide;  
use App\Enums\ReportType;  
use App\Enums\ExpensesType;
class COAAccount extends Model
{
    use SoftDeletes;
    protected $connection = 'tenant';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $table = 'coa_accounts';
    protected $fillable = [
        'company_id',
        'branch_id',
        'code',
        'name',
        'description',
        'parent_id',
        'type',
        'account_side',
        'report_type',
        'account_level',
        'is_main',
        'expenses_type',
        'is_posting',
        'is_active',
        'metadata',
    ];
    protected $casts = [
        'is_posting' => 'boolean',
        'is_active' => 'boolean',
        'is_main' => 'boolean',
        'metadata' => 'array',
        'account_side' => AccountSide::class,
        'report_type' => ReportType::class,
        'expenses_type' => ExpensesType::class,
    ];


    protected static function booted()
    {
        static::creating(function ($account) {
            if (empty($account->{$account->getKeyName()})) {
                $account->{$account->getKeyName()} = (string) \Illuminate\Support\Str::uuid();
            }
            
             
            if ($account->parent_id) {
                $parent = self::find($account->parent_id);
                $account->account_level = $parent->account_level + 1;
            } else {
                $account->account_level = 1;
            }
        });

        static::updating(function ($account) {
            if ($account->isDirty('parent_id')) {
                if ($account->parent_id) {
                    $parent = self::find($account->parent_id);
                    $account->account_level = $parent->account_level + 1;
                } else {
                    $account->account_level = 1;
                }
            }
        });
    }
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(COAAccount::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(COAAccount::class, 'parent_id');
    }

    public function integrations(): HasMany
    {
        return $this->hasMany(ChartOfAccountIntegration::class, 'account_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByBranch($query, $branchId = null)
    {
        if ($branchId) {
            return $query->where('branch_id', $branchId);
        }
        return $query->whereNull('branch_id');
    }

    public function scopeRootAccounts($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeByLevel($query, $level)
    {
        return $query->where('account_level', $level);
    }

    // Business logic methods
    public function hasChildren(): bool
    {
        return $this->children()->exists();
    }
    public function journalEntryRows(): HasMany
    {
        return $this->hasMany(JournalEntryRow::class, 'account_id');
    }
    public function hasPostedEntries(): bool
    {
        return $this->journalEntryRows()
            ->whereHas('journalEntry', function($query) {
                $query->where('status', 'posted');
            })
            ->exists();
    }
    public function journalEntryRowsWithEntries()
    {
        return $this->hasMany(JournalEntryRow::class, 'account_id')
            ->with('journalEntry');
    }
    public function getCurrentBalanceAttribute(): float
    {
        $debit = $this->postedJournalEntryRows()->sum('debit');
        $credit = $this->postedJournalEntryRows()->sum('credit');

        if ($this->account_side === 'debit') {
            return $debit - $credit;
        } else {
            return $credit - $debit;
        }
    }

    /**
     * Calculate balance for a specific period
     */
    public function getBalanceForPeriod(string $from, string $to): float
    {
        $debit = $this->postedJournalEntryRows()
            ->whereHas('journalEntry', function($query) use ($from, $to) {
                $query->whereBetween('issue_date', [$from, $to]);
            })
            ->sum('debit');

        $credit = $this->postedJournalEntryRows()
            ->whereHas('journalEntry', function($query) use ($from, $to) {
                $query->whereBetween('issue_date', [$from, $to]);
            })
            ->sum('credit');

        if ($this->account_side === 'debit') {
            return $debit - $credit;
        } else {
            return $credit - $debit;
        }
    }
    public function getFullPath(): string
    {
        $path = [];
        $current = $this;
        
        while ($current) {
            $path[] = $current->code . ' - ' . $current->name;
            $current = $current->parent;
        }
        
        return implode(' → ', array_reverse($path));
    }

     
}