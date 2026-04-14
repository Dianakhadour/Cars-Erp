<?php

namespace App\Models\Master;

use Laravel\Sanctum\PersonalAccessToken as SanctumToken;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class MasterPersonalAccessToken extends SanctumToken
{
    // use HasUuids;
    
      protected $connection = 'master';
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
    //     return $this->morphTo()->setMorphType('App\Models\Master\MasterUser');
    // }

    // protected static function booted()
    // {
    //     static::creating(function ($token) {
    //         if (empty($token->tokenable_type)) {
    //             $token->tokenable_type = \App\Models\Master\MasterUser::class;
    //         }
    //     });
    // }
}