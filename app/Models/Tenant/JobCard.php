<?php
 
namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class JobCard extends Model
{
    use HasFactory, SoftDeletes;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'company_id',
        'job_number',
        'vehicle_item_id',
        'customer_id',
        'customer_name',
        'customer_phone',
        'customer_email',
        'vehicle_license',
        'vehicle_vin',
        'vehicle_make',
        'vehicle_model',
        'vehicle_year',
        'vehicle_color',
        'vehicle_mileage',
        'service_type',
        'work_description',
        'customer_complaint',
        'technician_findings',
        'status',
        'estimated_start',
        'estimated_completion',
        'actual_start',
        'actual_completion',
        'estimated_labor_cost',
        'estimated_parts_cost',
        'actual_labor_cost',
        'actual_parts_cost',
        'total_estimated_cost',
        'total_actual_cost',
        'assigned_technician_id',
        'service_advisor_id',
        'workshop_bay_id',
        'technician_notes',
        'service_advisor_notes',
        'customer_notes',
    ];

    protected $casts = [
        'id' => 'string',
        'vehicle_item_id' => 'integer',  
        'customer_id' => 'string',
        'assigned_technician_id' => 'string',
        'service_advisor_id' => 'string',
        'estimated_start' => 'datetime',
        'estimated_completion' => 'datetime',
        'actual_start' => 'datetime',
        'actual_completion' => 'datetime',
        'estimated_labor_cost' => 'decimal:2',
        'estimated_parts_cost' => 'decimal:2',
        'actual_labor_cost' => 'decimal:2',
        'actual_parts_cost' => 'decimal:2',
        'total_estimated_cost' => 'decimal:2',
        'total_actual_cost' => 'decimal:2',
        'vehicle_year' => 'integer',
        'vehicle_mileage' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
            
            // Generate job number if not provided
            if (empty($model->job_number)) {
                $model->job_number = 'JC-' . date('Ymd') . '-' . strtoupper(Str::random(6));
            }
        });
    }

    

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function vehicleItem()
    {
        return $this->belongsTo(Item::class, 'vehicle_item_id')
                    ->where('item_type', 'vehicle');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function technician()
    {
        return $this->belongsTo(TenantUser::class, 'assigned_technician_id')
                    ->where('role', 'technician');
    }

    public function serviceAdvisor()
    {
        return $this->belongsTo(TenantUser::class, 'service_advisor_id')
                    ->where('role', 'service_advisor');
    }

    public function jobParts()
    {
        return $this->hasMany(JobPart::class);
    }

    public function issuedParts()
    {
        return $this->hasMany(JobPart::class)->where('status', 'issued');
    }

    public function usedParts()
    {
        return $this->hasMany(JobPart::class)->where('status', 'used');
    }

    public function reservedParts()
    {
        return $this->hasMany(JobPart::class)->where('status', 'reserved');
    }

    // Scopes
    public function scopeByCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['pending', 'in_progress', 'waiting_parts', 'waiting_approval']);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    // Methods
    public function calculatePartsCost()
    {
        $this->actual_parts_cost = $this->jobParts()
            ->whereIn('status', ['issued', 'used'])
            ->get()
            ->sum(function($jobPart) {
                return $jobPart->quantity_used * $jobPart->actual_unit_cost;
            });
            
        $this->total_actual_cost = $this->actual_labor_cost + $this->actual_parts_cost;
        $this->save();
    }

    public function getRemainingPartsCost()
    {
        return $this->jobParts()
            ->whereIn('status', ['pending', 'reserved'])
            ->get()
            ->sum(function($jobPart) {
                return $jobPart->quantity_required * $jobPart->estimated_unit_cost;
            });
    }

    // Helper method to get full vehicle description
    public function getVehicleDescriptionAttribute()
    {
        if ($this->vehicle_make && $this->vehicle_model && $this->vehicle_year) {
            return "{$this->vehicle_year} {$this->vehicle_make} {$this->vehicle_model}";
        }
        
        return $this->vehicle_license ?: 'No vehicle information';
    }
}