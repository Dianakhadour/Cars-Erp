<?php
 
namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class TenantUser extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'company_id',
        'name',
        'email',
        'password',
        'phone',
        'employee_id',
        'role',
        'technician_level',
        'specializations',
        'hourly_rate',
        'is_available',
        'hire_date',
        'termination_date',
        'is_active',
        'bio',
        'certifications',
        'profile_image',
        'permissions',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'id' => 'string',
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'specializations' => 'array',
        'certifications' => 'array',
        'permissions' => 'array',
        'hourly_rate' => 'decimal:2',
        'is_available' => 'boolean',
        'is_active' => 'boolean',
        'hire_date' => 'date',
        'termination_date' => 'date',
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

    public function assignedJobCards()
    {
        return $this->hasMany(JobCard::class, 'assigned_technician_id');
    }

    public function advisedJobCards()
    {
        return $this->hasMany(JobCard::class, 'service_advisor_id');
    }

    // Scopes
    public function scopeByCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeAvailable($query)
    {
        return $query->where('is_available', true);
    }

    public function scopeTechnicians($query)
    {
        return $query->where('role', 'technician');
    }

    public function scopeServiceAdvisors($query)
    {
        return $query->where('role', 'service_advisor');
    }

    public function scopeByTechnicianLevel($query, $level)
    {
        return $query->where('technician_level', $level);
    }

    public function scopeWithSpecialization($query, $specialization)
    {
        return $query->whereJsonContains('specializations', $specialization);
    }

    // Methods
    public function isTechnician()
    {
        return $this->role === 'technician';
    }

    public function isServiceAdvisor()
    {
        return $this->role === 'service_advisor';
    }

    public function isWorkshopManager()
    {
        return $this->role === 'workshop_manager';
    }

    public function isInventoryManager()
    {
        return $this->role === 'inventory_manager';
    }

    public function getSpecializationsListAttribute()
    {
        return $this->specializations ? implode(', ', $this->specializations) : 'None';
    }

    public function getCertificationsListAttribute()
    {
        return $this->certifications ? implode(', ', $this->certifications) : 'None';
    }

    public function getFormattedHourlyRateAttribute()
    {
        return '$' . number_format($this->hourly_rate, 2);
    }

    public function getExperienceYearsAttribute()
    {
        if (!$this->hire_date) {
            return 0;
        }

        return now()->diffInYears($this->hire_date);
    }
}