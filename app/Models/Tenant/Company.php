<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
class Company extends Model
{
    use HasFactory;
    protected $connection = 'tenant';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $fillable = ['name', 'cr_number', 'cr_issue_date'];

    protected $casts = [
        'cr_issue_date' => 'date',
    ];
    protected static function booted()
    {
        static::creating(function ($company) {
            if (empty($company->{$company->getKeyName()})) {
                $company->{$company->getKeyName()} = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(CompanyRegistration::class);
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function coaAccounts(): HasMany
    {
        return $this->hasMany(COAAccount::class);
    }

    public function numberSequences(): HasMany
    {
        return $this->hasMany(NumberSequence::class);
    }
      /**
     * Get the categories for the company.
     */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    /**
     * Get the items for the company.
     */
    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    /**
     * Get the warehouses for the company.
     */
    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class);
    }

    /**
     * Get the stock movements for the company.
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Get the stock valuations for the company.
     */
    public function stockValuations(): HasMany
    {
        return $this->hasMany(StockValuation::class);
    }
}