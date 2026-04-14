<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bin extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'warehouse_id',
        'code',
        'name',
        'description',
        'zone',
        'aisle',
        'rack',
        'shelf',
        'level',
        'length',
        'width',
        'height',
        'volume_capacity',
        'weight_capacity',
        'unit_capacity',
        'bin_type',
        'is_active',
        'is_occupied',
        'is_vehicle_space',
        'vehicle_space_type',
    ];

    protected $casts = [
        'length' => 'decimal:2',
        'width' => 'decimal:2',
        'height' => 'decimal:2',
        'volume_capacity' => 'decimal:2',
        'weight_capacity' => 'decimal:2',
        'is_active' => 'boolean',
        'is_occupied' => 'boolean',
        'is_vehicle_space' => 'boolean',
    ];

    /**
     * Get the company that owns the bin.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the warehouse that owns the bin.
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Get the source stock movements for the bin.
     */
    public function sourceStockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'source_bin_id');
    }

    /**
     * Get the destination stock movements for the bin.
     */
    public function destinationStockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'destination_bin_id');
    }

    /**
     * Get the stock valuations for the bin.
     */
    public function stockValuations(): HasMany
    {
        return $this->hasMany(StockValuation::class);
    }

    /**
     * Scope active bins.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope by bin type.
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('bin_type', $type);
    }

    /**
     * Scope vehicle spaces only.
     */
    public function scopeVehicleSpaces($query)
    {
        return $query->where('is_vehicle_space', true);
    }

    /**
     * Get full location path.
     */
    public function getFullLocationAttribute()
    {
        $location = [];
        if ($this->zone) $location[] = 'Zone ' . $this->zone;
        if ($this->aisle) $location[] = 'Aisle ' . $this->aisle;
        if ($this->rack) $location[] = 'Rack ' . $this->rack;
        if ($this->shelf) $location[] = 'Shelf ' . $this->shelf;
        if ($this->level) $location[] = 'Level ' . $this->level;
        
        return implode(' - ', $location) . ' (' . $this->code . ')';
    }

    /**
     * Calculate current utilization percentage.
     */
    public function getUtilizationPercentageAttribute()
    {
        if (!$this->volume_capacity) return 0;
        
        $usedVolume = $this->stockValuations()
            ->join('items', 'stock_valuations.item_id', '=', 'items.id')
            ->selectRaw('SUM(stock_valuations.quantity_on_hand * items.volume) as total_volume')
            ->value('total_volume') ?? 0;
            
        return min(100, ($usedVolume / $this->volume_capacity) * 100);
    }
}