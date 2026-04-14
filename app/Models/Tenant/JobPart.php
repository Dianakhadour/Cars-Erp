<?php
 
namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class JobPart extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'company_id',
        'job_card_id',
        'part_id',
        'warehouse_id',
        'quantity_required',
        'quantity_issued',
        'quantity_used',
        'quantity_returned',
        'estimated_unit_cost',
        'actual_unit_cost',
        'selling_price',
        'status',
        'reserved_at',
        'issued_at',
        'used_at',
        'returned_at',
        'reservation_movement_id',
        'issue_movement_id',
        'return_movement_id',
        'notes',
    ];

    protected $casts = [
        'id' => 'string',
        'quantity_required' => 'decimal:4',
        'quantity_issued' => 'decimal:4',
        'quantity_used' => 'decimal:4',
        'quantity_returned' => 'decimal:4',
        'estimated_unit_cost' => 'decimal:4',
        'actual_unit_cost' => 'decimal:4',
        'selling_price' => 'decimal:4',
        'reserved_at' => 'datetime',
        'issued_at' => 'datetime',
        'used_at' => 'datetime',
        'returned_at' => 'datetime',
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

    public function jobCard()
    {
        return $this->belongsTo(JobCard::class);
    }

    public function part()
    {
        return $this->belongsTo(Part::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function reservationMovement()
    {
        return $this->belongsTo(PartStockMovement::class, 'reservation_movement_id');
    }

    public function issueMovement()
    {
        return $this->belongsTo(PartStockMovement::class, 'issue_movement_id');
    }

    public function returnMovement()
    {
        return $this->belongsTo(PartStockMovement::class, 'return_movement_id');
    }

    // Scopes
    public function scopeByCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeByJob($query, $jobCardId)
    {
        return $query->where('job_card_id', $jobCardId);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeReserved($query)
    {
        return $query->where('status', 'reserved');
    }

    public function scopeIssued($query)
    {
        return $query->where('status', 'issued');
    }

    // Methods
    public function getQuantityAvailableForUse()
    {
        return $this->quantity_issued - $this->quantity_used;
    }

    public function getQuantityAvailableForReturn()
    {
        return $this->quantity_issued - $this->quantity_used - $this->quantity_returned;
    }

    public function getTotalEstimatedCost()
    {
        return $this->quantity_required * $this->estimated_unit_cost;
    }

    public function getTotalActualCost()
    {
        return $this->quantity_used * $this->actual_unit_cost;
    }

    public function getTotalSellingPrice()
    {
        return $this->quantity_used * $this->selling_price;
    }
}