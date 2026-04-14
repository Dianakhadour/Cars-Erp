<?php

namespace App\Models\Tenant;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class StockMovement extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'item_id',
        'movement_type',
        'quantity',
        'unit_cost',
        'total_value',
        'source_warehouse_id',
        'source_bin_id',
        'destination_warehouse_id',
        'destination_bin_id',
        'referenceable_id',
        'referenceable_type',
        'reference_number',
        'lot_number',
        'serial_number',
        'expiry_date',
        'vehicle_attributes',
        'status',
        'notes',
        'reason',
        'created_by',
        'movement_date',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'unit_cost' => 'decimal:4',
        'total_value' => 'decimal:4',
        'vehicle_attributes' => 'array',
        'movement_date' => 'datetime',
    ];

    /**
     * Get the company that owns the stock movement.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the item that owns the stock movement.
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * Get the source warehouse.
     */
    public function sourceWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'source_warehouse_id');
    }

    /**
     * Get the source bin.
     */
    public function sourceBin(): BelongsTo
    {
        return $this->belongsTo(Bin::class, 'source_bin_id');
    }

    /**
     * Get the destination warehouse.
     */
    public function destinationWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'destination_warehouse_id');
    }

    /**
     * Get the destination bin.
     */
    public function destinationBin(): BelongsTo
    {
        return $this->belongsTo(Bin::class, 'destination_bin_id');
    }

    /**
     * Get the user who created the movement.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the parent referenceable model.
     */
    public function referenceable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope incoming movements.
     */
    public function scopeIncoming($query)
    {
        return $query->whereIn('movement_type', [
            'purchase_receipt', 'transfer_in', 'adjustment_in', 'production_in', 'sale_return'
        ]);
    }

    /**
     * Scope outgoing movements.
     */
    public function scopeOutgoing($query)
    {
        return $query->whereIn('movement_type', [
            'sale_issue', 'transfer_out', 'adjustment_out', 'production_out', 'purchase_return', 'waste_scrap'
        ]);
    }

    /**
     * Scope completed movements.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Check if movement is incoming.
     */
    public function getIsIncomingAttribute(): bool
    {
        return in_array($this->movement_type, [
            'purchase_receipt', 'transfer_in', 'adjustment_in', 'production_in', 'sale_return'
        ]);
    }

    /**
     * Check if movement is outgoing.
     */
    public function getIsOutgoingAttribute(): bool
    {
        return in_array($this->movement_type, [
            'sale_issue', 'transfer_out', 'adjustment_out', 'production_out', 'purchase_return', 'waste_scrap'
        ]);
    }

    /**
     * Get absolute quantity (always positive).
     */
    public function getAbsoluteQuantityAttribute()
    {
        return abs($this->quantity);
    }
}