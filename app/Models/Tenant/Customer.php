<?php
 
namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'company_id',
        'name',
        'email',
        'phone',
        'mobile',
        'address',
        'city',
        'state',
        'zip_code',
        'country',
        'type',
        'company_name',
        'tax_number',
        'contact_persons',
        'customer_class',
        'credit_limit',
        'current_balance',
        'is_active',
        'notes',
        'preferences',
    ];

    protected $casts = [
        'id' => 'string',
        'contact_persons' => 'array',
        'preferences' => 'array',
        'credit_limit' => 'decimal:2',
        'current_balance' => 'decimal:2',
        'is_active' => 'boolean',
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

    public function jobCards()
    {
        return $this->hasMany(JobCard::class);
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

    public function scopeIndividual($query)
    {
        return $query->where('type', 'individual');
    }

    public function scopeCorporate($query)
    {
        return $query->where('type', 'corporate');
    }

    public function scopePremium($query)
    {
        return $query->where('customer_class', 'premium');
    }

    public function scopeVip($query)
    {
        return $query->where('customer_class', 'vip');
    }

    // Methods
    public function getFullAddressAttribute()
    {
        $addressParts = array_filter([
            $this->address,
            $this->city,
            $this->state,
            $this->zip_code,
            $this->country,
        ]);

        return implode(', ', $addressParts) ?: 'No address provided';
    }

    public function getContactPersonsListAttribute()
    {
        if (empty($this->contact_persons)) {
            return [];
        }

        return array_map(function($person) {
            return "{$person['name']} - {$person['email']}";
        }, $this->contact_persons);
    }

    public function getAvailableCreditAttribute()
    {
        return $this->credit_limit - $this->current_balance;
    }
}