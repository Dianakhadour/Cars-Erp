<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserRole extends Pivot
{
    protected $table = 'user_roles';
    protected $connection = 'tenant';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',  
        'user_id',
        'role_id',
    ];


    protected $casts = [
        'user_id' => 'string',
        'role_id' => 'string',
    ];

    protected static function booted()
    {
        static::creating(function ($userRole) {
            if (empty($userRole->{$userRole->getKeyName()})) {
                $userRole->{$userRole->getKeyName()} = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }

    /**
     * Get the user that owns the user role
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the role that owns the user role
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Scope to find by user ID
     */
    public function scopeForUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to find by role ID
     */
    public function scopeForRole($query, string $roleId)
    {
        return $query->where('role_id', $roleId);
    }

    /**
     * Scope to find by role slug
     */
    public function scopeForRoleSlug($query, string $roleSlug)
    {
        return $query->whereHas('role', function ($q) use ($roleSlug) {
            $q->where('slug', $roleSlug);
        });
    }

    /**
     * Check if user has specific role
     */
    public static function userHasRole(string $userId, string $roleSlug): bool
    {
        return static::forUser($userId)
            ->forRoleSlug($roleSlug)
            ->exists();
    }

    /**
     * Get all roles for a user
     */
    public static function getUserRoles(string $userId): array
    {
        return static::forUser($userId)
            ->with('role')
            ->get()
            ->pluck('role.slug')
            ->toArray();
    }
}