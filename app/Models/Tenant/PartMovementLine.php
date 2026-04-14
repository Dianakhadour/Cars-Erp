<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class PartMovementLine extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'company_id',
        'movement_id',
        'part_id',
        'quantity',
        'unit_cost',
        'total_cost',
        'batch_number',
        'serial_number',
        'expiry_date',
        'warehouse_id',
        'location_id',
        'status',
        'notes'
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'unit_cost' => 'decimal:4',
        'total_cost' => 'decimal:4',
        'expiry_date' => 'date',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
            
            // Auto-calculate total cost
            if (empty($model->total_cost)) {
                $model->total_cost = $model->quantity * $model->unit_cost;
            }
        });

        static::created(function ($model) {
            // Update stock valuation when line is created
            $model->updateStockValuation();
        });
    }

    // Relationships
    public function movement()
    {
        return $this->belongsTo(PartStockMovement::class, 'movement_id');
    }
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

    public function location()
    {
        return $this->belongsTo(Location::class); 
    }

    // Methods
    public function updateStockValuation()
    {
        if ($this->status !== 'completed') {
            return;
        }

        $movement = $this->movement;
        if (!$movement) return;

        $valuation = PartStockValuation::firstOrCreate([
            'part_id' => $this->part_id,
            'warehouse_id' => $this->warehouse_id,
            'company_id' => $this->company_id,
        ]);

        // Update quantities based on movement type
        switch ($movement->movement_type) {
            case 'receipt':
                $valuation->quantity_on_hand += $this->quantity;
                break;
            case 'issue':
                $valuation->quantity_on_hand -= $this->quantity;
                break;
            case 'transfer':
                
                if ($movement->source_location === $this->warehouse_id) {
                    $valuation->quantity_on_hand -= $this->quantity;
                } else {
                    $valuation->quantity_on_hand += $this->quantity;
                }
                break;
        }

        // Update costs for receipts
        if ($movement->movement_type === 'receipt' && $this->unit_cost > 0) {
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