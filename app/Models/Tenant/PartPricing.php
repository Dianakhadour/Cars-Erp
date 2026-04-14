<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PartPricing extends Model
{
    use HasFactory;

    protected $table = 'part_pricings';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['id',
        'company_id',
        'part_id',
        'pricing_type',
        'price',
        'currency',
        'min_quantity',
        'effective_from',
        'effective_to',
        'is_active'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'min_quantity' => 'integer',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'is_active' => 'boolean',
    ];

    public function part()
    {
        return $this->belongsTo(Part::class, 'part_id');
    }
}