<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockValuation extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'item_id',
        'warehouse_id',
        'bin_id',
        'quantity_on_hand',
        'quantity_reserved',
        'average_cost',
        'total_value',
        'last_purchase_cost',
        'cost_layers',
        'last_receipt_date',
        'last_issue_date',
        'last_receipt_quantity',
        'last_issue_quantity',
        'days_in_stock',
        'turnover_rate',
        'current_market_value',
        'depreciation_rate',
    ];

    protected $casts = [
        'quantity_on_hand' => 'decimal:4',
        'quantity_reserved' => 'decimal:4',
        'quantity_available' => 'decimal:4',
        'average_cost' => 'decimal:4',
        'total_value' => 'decimal:4',
        'last_purchase_cost' => 'decimal:4',
        'cost_layers' => 'array',
        'last_receipt_quantity' => 'decimal:4',
        'last_issue_quantity' => 'decimal:4',
        'turnover_rate' => 'decimal:2',
        'current_market_value' => 'decimal:4',
        'depreciation_rate' => 'decimal:2',
        'last_receipt_date' => 'datetime',
        'last_issue_date' => 'datetime',
    ];

    /**
     * Get the company that owns the stock valuation.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the item that owns the stock valuation.
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * Get the warehouse that owns the stock valuation.
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Get the bin that owns the stock valuation.
     */
    public function bin(): BelongsTo
    {
        return $this->belongsTo(Bin::class);
    }

    /**
     * Scope by warehouse.
     */
    public function scopeForWarehouse($query, $warehouseId)
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    /**
     * Scope by item.
     */
    public function scopeForItem($query, $itemId)
    {
        return $query->where('item_id', $itemId);
    }

    /**
     * Scope items with stock.
     */
    public function scopeInStock($query)
    {
        return $query->where('quantity_on_hand', '>', 0);
    }

    /**
     * Scope low stock items.
     */
    public function scopeLowStock($query)
    {
        return $query->whereRaw('quantity_available <= items.min_stock_level')
                    ->join('items', 'stock_valuations.item_id', '=', 'items.id');
    }

    /**
     * Scope out of stock items.
     */
    public function scopeOutOfStock($query)
    {
        return $query->where('quantity_available', '<=', 0);
    }

    /**
     * Get the item's reorder status.
     */
    public function getReorderStatusAttribute()
    {
        if ($this->quantity_available <= 0) {
            return 'out_of_stock';
        } elseif ($this->quantity_available <= $this->item->reorder_point) {
            return 'reorder';
        } else {
            return 'adequate';
        }
    }

    /**
     * Calculate stock value based on item's cost method.
     */
    public function calculateStockValue()
    {
        // This would implement the specific costing method (FIFO, LIFO, etc.)
        // For now, using average cost
        return $this->quantity_on_hand * $this->average_cost;
    }
}