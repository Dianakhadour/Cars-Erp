<?php

namespace App\Models\Tenant;

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PartStockMovement extends Model
{
    use HasFactory, SoftDeletes;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'company_id',
        'part_id',
        'warehouse_id',
        'movement_type',
        'quantity',
        'unit_cost',
        'total_cost',
        'reference_type',
        'reference_id',
        'document_number',
        'source_location',
        'destination_location',
        'status',
        'notes',
        'created_by',
        'movement_date'
    ];

    protected $casts = [  'id' => 'string', 
        'quantity' => 'decimal:4',
        'unit_cost' => 'decimal:4',
        'total_cost' => 'decimal:4',
        'movement_date' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
            
            if (empty($model->movement_date)) {
                $model->movement_date = now();
            }
        });

        static::created(function ($model) {
             
            $model->updateStockValuation();
        });
    }
    public function lines()
    {
        return $this->hasMany(PartMovementLine::class, 'movement_id');  
    }

    public function sourceWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'source_location');
    }

    public function destinationWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'destination_location');
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

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scopes
    public function scopeByCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeByPart($query, $partId)
    {
        return $query->where('part_id', $partId);
    }

    public function scopeByWarehouse($query, $warehouseId)
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('movement_date', [$startDate, $endDate]);
    }

    public function scopeReceipts($query)
    {
        return $query->where('movement_type', 'receipt');
    }

    public function scopeIssues($query)
    {
        return $query->where('movement_type', 'issue');
    }

    // Methods
    public function updateStockValuation()
    {
        if ($this->status !== 'completed') {
            return;
        }
        if ($this->lines()->exists()) {
            $this->lines->each->updateStockValuation();
            return;
        }
        $valuation = PartStockValuation::firstOrCreate([
            'part_id' => $this->part_id,
            'warehouse_id' => $this->warehouse_id,
            'company_id' => $this->company_id,
        ]);
       
        // Update quantities based on movement type
        switch ($this->movement_type) {
            case 'receipt':
                $valuation->quantity_on_hand += $this->quantity;
                break;
            case 'issue':
                $valuation->quantity_on_hand -= $this->quantity;
                break;
            case 'adjustment':
                $valuation->quantity_on_hand = $this->quantity; // Direct set for adjustments
                break;
            case 'reservation':
                $valuation->quantity_reserved += $this->quantity;
                break;
        }

        // Update costs for receipts
        if ($this->movement_type === 'receipt' && $this->unit_cost > 0) {
            $totalValue = $valuation->quantity_on_hand * $valuation->average_cost;
            $newValue = $this->quantity * $this->unit_cost;
            
            if ($valuation->quantity_on_hand > 0) {
                $valuation->average_cost = ($totalValue + $newValue) / $valuation->quantity_on_hand;
            }
            
            $valuation->last_cost = $this->unit_cost;
        }

        $valuation->total_value = $valuation->quantity_on_hand * $valuation->average_cost;
        $valuation->last_movement_at = now();
        $valuation->updateStockStatus();
        
        $valuation->save();
    }
}