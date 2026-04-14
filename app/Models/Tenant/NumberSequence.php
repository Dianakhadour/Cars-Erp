<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NumberSequence extends Model
{  
    protected $connection = 'tenant';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'doc_type',
        'prefix',
        'next_number',
        'padding',
        'yearly_reset',
    ];

    protected $casts = [
        'yearly_reset' => 'boolean',
    ];

    protected static function booted()
    {
        static::creating(function ($sequence) {
            if (empty($sequence->{$sequence->getKeyName()})) {
                $sequence->{$sequence->getKeyName()} = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Generate next sequence number
     */
    public function getNextNumber(): string
    {
        $number = $this->next_number;
        $paddedNumber = str_pad($number, $this->padding, '0', STR_PAD_LEFT);
        
        $this->increment('next_number');
        
        return $this->prefix . $paddedNumber;
    }
}