<?php
 
namespace App\Models\Tenant;

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PartsPurchaseOrder extends Model
{
    use HasFactory, SoftDeletes;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'company_id',
        'supplier_id',
        'warehouse_id',
        'po_number',
        'order_date',
        'expected_delivery_date',
        'status',
        'subtotal',
        'tax_amount',
        'shipping_cost',
        'total_amount',
        'received_amount',
        'shipping_address',
        'notes',
        'created_by',
        'ordered_at',
        'completed_at'
    ];

    protected $casts = [
        'order_date' => 'date',
        'expected_delivery_date' => 'date',
        'ordered_at' => 'datetime',
        'completed_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'received_amount' => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
            
            
            if (empty($model->po_number)) {
                $model->po_number = 'PO-' . date('Ymd') . '-' . Str::random(6);
            }
        });
    }

    // Relationships
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function lines()
    {
        return $this->hasMany(PartsPurchaseOrderLine::class, 'purchase_order_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    public function updateStatus()
    {
        $totalLines = $this->lines->count();
        $fullyReceivedLines = $this->lines->where('is_fully_received', true)->count();
        $partiallyReceivedLines = $this->lines->where('quantity_received', '>', 0)->count();
    
        if ($fullyReceivedLines === $totalLines) {
            // All lines fully received
            $this->status = 'completed';
            $this->completed_at = now();
        } elseif ($partiallyReceivedLines > 0) {
            // Some lines received
            $this->status = 'partially_received';
        } elseif ($this->status === 'draft' && $this->ordered_at) {
            // Ordered but nothing received yet
            $this->status = 'ordered';
        }
        
        $this->save();
    }
    // Scopes
    public function scopeByCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeOrdered($query)
    {
        return $query->where('status', 'ordered');
    }

    public function scopePendingReceipt($query)
    {
        return $query->whereIn('status', ['ordered', 'partially_received']);
    }

    // Methods
    public function updateTotals()
    {
        $this->subtotal = $this->lines->sum('line_total');
        $this->total_amount = $this->subtotal + $this->tax_amount + $this->shipping_cost;
        $this->save();
    }

    public function markAsOrdered()
    {
        $this->status = 'ordered';
        $this->ordered_at = now();
        $this->save();
    }

    public function getProgressPercentage()
    {
        if ($this->total_amount == 0) return 0;
        return ($this->received_amount / $this->total_amount) * 100;
    }
}