<?php
 namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class PartsPurchaseOrderLine extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'company_id',
        'purchase_order_id',
        'part_id',
        'quantity_ordered',
        'quantity_received',
        'unit_price',
        'line_total'
    ];

    protected $casts = [
        'quantity_ordered' => 'decimal:4',
        'quantity_received' => 'decimal:4',
        'unit_price' => 'decimal:4',
        'line_total' => 'decimal:4',
        'is_fully_received' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
            
            // Calculate line total
            if (empty($model->line_total)) {
                $model->line_total = $model->quantity_ordered * $model->unit_price;
            }
        });

        static::updated(function ($model) {
            // Update fully received status
            $model->is_fully_received = $model->quantity_received >= $model->quantity_ordered;
            $model->saveQuietly();  

           
            $model->purchaseOrder->update([
                'received_amount' => $model->purchaseOrder->lines->sum(function($line) {
                    return $line->quantity_received * $line->unit_price;
                })
            ]);

            // Update PO status
            $model->purchaseOrder->updateStatus();
        });
    }

    // Relationships
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PartsPurchaseOrder::class, 'purchase_order_id');
    }

    public function part()
    {
        return $this->belongsTo(Part::class);
    }

    // Methods
    public function receiveQuantity($quantity)
    {
        $this->quantity_received += $quantity;
        $this->save();
    }

    public function getPendingQuantity()
    {
        return $this->quantity_ordered - $this->quantity_received;
    }
}