<?php

namespace App\Models\Tenant;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Warehouse extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'description',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'postal_code',
        'country',
        'latitude',
        'longitude',
        'contact_person',
        'phone',
        'email',
        'type',
        'total_area',
        'usable_area',
        'capacity_units',
        'is_active',
        'is_default',
        'allow_negative_stock',
        'manager_id',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'total_area' => 'decimal:2',
        'usable_area' => 'decimal:2',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'allow_negative_stock' => 'boolean',
    ];

    /**
     * Get the company that owns the warehouse.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the manager that owns the warehouse.
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    /**
     * Get the bins for the warehouse.
     */
    public function bins(): HasMany
    {
        return $this->hasMany(Bin::class);
    }

    /**
     * Get the source stock movements for the warehouse.
     */
    public function sourceStockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'source_warehouse_id');
    }

    /**
     * Get the destination stock movements for the warehouse.
     */
    public function destinationStockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'destination_warehouse_id');
    }

    /**
     * Get the stock valuations for the warehouse.
     */
    public function stockValuations(): HasMany
    {
        return $this->hasMany(StockValuation::class);
    }

    /**
     * Scope active warehouses.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope by type.
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Get total inventory value in this warehouse.
     */
    public function getTotalInventoryValueAttribute()
    {
        return $this->stockValuations()->sum('total_value');
    }

    /**
     * Get address as a formatted string.
     */
    public function getFullAddressAttribute()
    {
        $address = $this->address_line_1;
        if ($this->address_line_2) {
            $address .= ', ' . $this->address_line_2;
        }
        $address .= ', ' . $this->city . ', ' . $this->state . ' ' . $this->postal_code;
        return $address;
    }
}