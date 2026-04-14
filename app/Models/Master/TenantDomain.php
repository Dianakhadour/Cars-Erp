<?php
namespace App\Models\Master;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;

class TenantDomain extends Model
{
    protected $fillable = ['tenant_id', 'domain'];
    public $incrementing = false;   
    protected $keyType = 'string';
    public function tenant() 
    {
        return $this->belongsTo(Tenant::class);
    }
    protected static function booted()
    {
        static::creating(function ($tenant) {
            if (empty($tenant->{$tenant->getKeyName()})) {
                $tenant->{$tenant->getKeyName()} = (string) Str::uuid();
            }
        });
    }
}
