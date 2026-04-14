<?php

namespace App\Models\Master;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    protected $fillable = ['name', 'slug', 'database_name'];
    public $incrementing = false;   
    protected $keyType = 'string';  
    public function domains(): HasMany
    {
        return $this->hasMany(TenantDomain::class);
    }  
    public function license()
    {
        return $this->hasOne(License::class);
    }
    protected static function booted()
    {
        static::creating(function ($tenant) {
            if (empty($tenant->{$tenant->getKeyName()})) {
                $tenant->{$tenant->getKeyName()} = (string) Str::uuid();
            }
        });
    }
    public function scopeByDomain($query, $domain)
    {
        return $query->where('domain', $domain);
    }
}
