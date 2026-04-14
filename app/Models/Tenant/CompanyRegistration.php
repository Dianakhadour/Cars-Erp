<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyRegistration extends Model
{
    protected $connection = 'tenant';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'type',
        'value',
        'issue_date',
    ];

    protected $casts = [
        'issue_date' => 'date',
    ];

    protected static function booted()
    {
        static::creating(function ($registration) {
            if (empty($registration->{$registration->getKeyName()})) {
                $registration->{$registration->getKeyName()} = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}