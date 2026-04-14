<?php
namespace App\Models;

use Illuminate\Support\Str;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
 

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;  

    protected $connection = 'tenant';
    protected $keyType = 'string';
    public $incrementing = false;
    
    protected $fillable = [
        'name',
        'email',
        'password',
        'active',
    ];
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'active' => 'boolean',
        ];
    }
     
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }
     
    public function getJWTCustomClaims(): array
    {
        return [
            'tenant_id' => $this->tenant_id ?? null,  
            'user_type' => 'tenant'
        ];
    }
    protected static function booted()
    {
        static::creating(function ($tenant) {
            if (empty($tenant->{$tenant->getKeyName()})) {
                $tenant->{$tenant->getKeyName()} = (string) Str::uuid();
            }
        });
    }
   
    // public function tokens()
    // {
    //     return $this->morphMany(PersonalAccessToken::class, 'tokenable');
    // }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_roles')
                    ->using(UserRole::class)  
                    ->withTimestamps();
    }

    public function branches()
    {
        return $this->belongsToMany(Branch::class, 'user_branches');
    }

     
    public function hasPermission(string $permission): bool
    {
        return $this->roles()
            ->whereHas('permissions', function ($query) use ($permission) {
                $query->where('name', $permission);
            })
            ->exists();
    }
 
    public function hasBranchAccess(string $branchId): bool
    {
        // If user has all branches permission or specific branch access
        return $this->hasPermission('branch.all') || 
               $this->branches()->where('branch_id', $branchId)->exists();
    }
    /**
     * Check if user has specific role
     */
    public function hasRole(string $roleSlug): bool
    {
        return $this->roles()->where('slug', $roleSlug)->exists();
    }
    public function hasAnyRole(array $roles): bool
    {
        return $this->roles()->whereIn('slug', $roles)->exists();
    }
     /**
     * Get the warehouses managed by the user.
     */
    public function managedWarehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class, 'manager_id');
    }

    /**
     * Get the stock movements created by the user.
     */
    public function createdStockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'created_by');
    }
}
