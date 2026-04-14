<?php

namespace App\Models\Master;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class License extends Model
{
    protected $connection = 'master';
    protected $keyType = 'string';
    public $incrementing = false;   
    protected $fillable = [
        'tenant_id',
        'plan_code',
        'max_companies',
        'max_branches_per_company',
        'features_json',
        'starts_at',
        'ends_at',
        'status',
    ];

    protected $casts = [
        'features_json' => 'array',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];
    protected static function booted()
    {
        static::creating(function ($license) {
            if (empty($license->{$license->getKeyName()})) {
                $license->{$license->getKeyName()} = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
