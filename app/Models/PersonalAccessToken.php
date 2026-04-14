<?php

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumToken;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
class PersonalAccessToken extends SanctumToken
{

    //use HasUuids;
    
    protected $connection = 'tenant';
     
    // protected $keyType = 'string';
    // public $incrementing = false;
    protected $table = 'personal_access_tokens';

    protected $fillable = [
        'name',
        'token',
        'abilities',
        'expires_at',
        'tokenable_id',
        'tokenable_type'
    ];
    protected $casts = [
        'abilities' => 'json',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];
    // public function tokenable()
    // {
    //     // Detect which DB we're currently using
    //     $connection = $this->getConnectionName() ?? config('database.default');

    //     if ($connection === 'master') {
    //         return $this->morphTo()->setMorphType('App\Models\Master\MasterUser');
    //     }

    //     return $this->morphTo()->setMorphType('App\Models\Tenant\TenantUser');
    // }
    // protected static function booted()
    // {
    //     static::creating(function ($token) {
            
    //         if (empty($token->tokenable_type)) {
    //             $token->tokenable_type = \App\Models\User::class;
    //         }
    //     });
    // }
}