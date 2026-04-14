<?php
 namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class PartStockValuation extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'company_id',
        'part_id',
        'warehouse_id',
        'quantity_on_hand',
        'quantity_reserved',
        'quantity_committed',
        'average_cost',
        'last_cost',
        'total_value',
        'is_low_stock',
        'is_out_of_stock',
        'last_movement_at'
    ];

    protected $casts = [  'id' => 'string', 
        'quantity_on_hand' => 'decimal:4',
        'quantity_reserved' => 'decimal:4',
        'quantity_available' => 'decimal:4',
        'quantity_committed' => 'decimal:4',
        'average_cost' => 'decimal:4',
        'last_cost' => 'decimal:4',
        'total_value' => 'decimal:4',
        'is_low_stock' => 'boolean',
        'is_out_of_stock' => 'boolean',
        'last_movement_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    // Relationships
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function part()
    {
        return $this->belongsTo(Part::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    // Scopes
    public function scopeByCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeLowStock($query)
    {
        return $query->where('is_low_stock', true);
    }

    public function scopeOutOfStock($query)
    {
        return $query->where('is_out_of_stock', true);
    }

    public function scopeByPart($query, $partId)
    {
        return $query->where('part_id', $partId);
    }

    public function scopeByWarehouse($query, $warehouseId)
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    // Methods
    public function updateStockStatus()
    {
        $part = $this->part;
        
        $this->is_low_stock = $this->quantity_available <= $part->reorder_point;
        $this->is_out_of_stock = $this->quantity_available <= 0;
        
        $this->save();
    }

    public function getAvailableQuantityAttribute()
    {
        return $this->quantity_on_hand - $this->quantity_reserved;
    }
}