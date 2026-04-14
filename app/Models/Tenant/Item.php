<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Item extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'category_id',
        'sku',
        'name',
        'barcode',
        'description',
        'item_type',
        'vin',
        'make',
        'model',
        'year',
        'color',
        'mileage',
        'vehicle_status',
        'vehicle_features',
        'unit_of_measure',
        'weight',
        'volume',
        'cost_method',
        'standard_cost',
        'last_purchase_cost',
        'selling_price',
        'min_price',
        'max_price',
        'min_stock_level',
        'max_stock_level',
        'reorder_point',
        'economic_order_qty',
        'is_active',
        'is_taxable',
        'is_serialized',
        'is_lot_tracked',
        'images',
    ];

    protected $casts = [
        'vehicle_features' => 'array',
        'images' => 'array',
        'standard_cost' => 'decimal:2',
        'last_purchase_cost' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'min_price' => 'decimal:2',
        'max_price' => 'decimal:2',
        'weight' => 'decimal:2',
        'volume' => 'decimal:2',
        'is_active' => 'boolean',
        'is_taxable' => 'boolean',
        'is_serialized' => 'boolean',
        'is_lot_tracked' => 'boolean',
    ];

    /**
     * Get the company that owns the item.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the category that owns the item.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the stock movements for the item.
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Get the stock valuations for the item.
     */
    public function stockValuations(): HasMany
    {
        return $this->hasMany(StockValuation::class);
    }

    /**
     * Scope active items.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope by item type.
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('item_type', $type);
    }

    /**
     * Scope vehicles only.
     */
    public function scopeVehicles($query)
    {
        return $query->where('item_type', 'vehicle');
    }

    /**
     * Scope parts only.
     */
    public function scopeParts($query)
    {
        return $query->where('item_type', 'part');
    }
    public function scopeByCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }
    /**
     * Get total quantity on hand across all warehouses.
     */
    public function getTotalQuantityOnHandAttribute()
    {
        return $this->stockValuations()->sum('quantity_on_hand');
    }

    /**
     * Get total quantity reserved across all warehouses.
     */
    public function getTotalQuantityReservedAttribute()
    {
        return $this->stockValuations()->sum('quantity_reserved');
    }

    /**
     * Get total quantity available across all warehouses.
     */
    public function getTotalQuantityAvailableAttribute()
    {
        return $this->stockValuations()->sum('quantity_available');
    }

    /**
     * Check if item is low stock.
     */
    public function getIsLowStockAttribute()
    {
        return $this->total_quantity_available <= $this->min_stock_level;
    }

    /**
     * Check if item is out of stock.
     */
    public function getIsOutOfStockAttribute()
    {
        return $this->total_quantity_available <= 0;
    }
}