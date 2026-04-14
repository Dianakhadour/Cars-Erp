<?php

namespace App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;
class Part extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['id',
        'company_id',
        'category_id',
        'sku',
        'name',
        'description',
        'barcode',
        'oem_number',
        'brand',
        'model_compatibility',
        'unit_of_measure',
        'weight',
        'volume',
        'cost_price',
        'selling_price',
        'retail_price',
        'min_stock_level',
        'max_stock_level',
        'reorder_point',
        'is_active',
        'is_trackable',
        'specifications',
        'notes'
    ];

    protected $casts = ['id' => 'string',
        'model_compatibility' => 'array',
        'specifications' => 'array',
        'cost_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'retail_price' => 'decimal:2',
        'min_stock_level' => 'decimal:2',
        'max_stock_level' => 'decimal:2',
        'reorder_point' => 'decimal:2',
        'weight' => 'decimal:2',
        'volume' => 'decimal:2',
        'is_active' => 'boolean',
        'is_trackable' => 'boolean',
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

    public function category()
    {
        return $this->belongsTo(PartCategory::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class, 'item_id');
    }

    public function stockValuations()
    {
        return $this->hasMany(StockValuation::class, 'item_id');
    }

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrderLine::class, 'item_id');
    }
    public function purchaseOrderLines()
    {
        return $this->hasMany(PartsPurchaseOrderLine::class, 'part_id');
    }

    public function partsPurchaseOrderLines()
    {
        return $this->hasMany(PartsPurchaseOrderLine::class, 'part_id');
    }
    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeSearch($query, $searchTerm)
    {
        return $query->where(function($q) use ($searchTerm) {
            $q->where('sku', 'like', "%{$searchTerm}%")
              ->orWhere('name', 'like', "%{$searchTerm}%")
              ->orWhere('oem_number', 'like', "%{$searchTerm}%")
              ->orWhere('barcode', 'like', "%{$searchTerm}%")
              ->orWhere('brand', 'like', "%{$searchTerm}%");
        });
    }

    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

     
    
    
    public function currentStock()
    {
        return $this->hasOne(PartStockValuation::class)
            ->where('warehouse_id', $this->getDefaultWarehouseId())  
            ->withDefault([
                'quantity_on_hand' => 0,
                'quantity_reserved' => 0,
                'quantity_available' => 0,
            ]);
    }

    
    public function getTotalQuantityOnHandAttribute()
    {
        return $this->stockValuations->sum('quantity_on_hand');
    }

    public function getTotalQuantityReservedAttribute()
    {
        return $this->stockValuations->sum('quantity_reserved');
    }

    public function getTotalQuantityAvailableAttribute()
    {
        return $this->stockValuations->sum('quantity_available');
    }

    public function getIsLowStockAttribute()
    {
        return $this->stockValuations->where('is_low_stock', true)->count() > 0;
    }

    public function getIsOutOfStockAttribute()
    {
        return $this->stockValuations->where('is_out_of_stock', true)->count() > 0;
    }public function getCurrentStockAttribute()
    {
        
        return $this->stockValuations()->sum('quantity_on_hand');
    }
}