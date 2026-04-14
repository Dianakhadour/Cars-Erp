<?php
  
namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChartOfAccountIntegration extends Model
{
    use SoftDeletes;
    
    protected $connection = 'tenant';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $table = 'chart_of_account_integrations';
    
    protected $fillable = [
        'company_id',
        'account_id',
        'branch_id',
        'integration_type',
        'document_type',
        'priority',
        'metadata',
    ];

    protected $casts = [
        'priority' => 'integer',
        'metadata' => 'array',
    ];

    protected static function booted()
    {
        static::creating(function ($integration) {
            if (empty($integration->{$integration->getKeyName()})) {
                $integration->{$integration->getKeyName()} = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(COAAccount::class, 'account_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    // Scopes
    public function scopeByIntegrationType($query, $type)
    {
        return $query->where('integration_type', $type);
    }

    public function scopeByDocumentType($query, $documentType)
    {
        return $query->where('document_type', $documentType);
    }

    public function scopeByBranch($query, $branchId = null)
    {
        if ($branchId) {
            return $query->where('branch_id', $branchId);
        }
        return $query->whereNull('branch_id');
    }

    public function scopeActiveAccount($query)
    {
        return $query->whereHas('account', function ($q) {
            $q->where('is_active', true);
        });
    }

    public function scopeOrderedByPriority($query)
    {
        return $query->orderBy('priority', 'desc')->orderBy('created_at', 'desc');
    }
}