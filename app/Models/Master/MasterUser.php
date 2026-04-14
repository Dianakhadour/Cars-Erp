<?php

namespace App\Models\Master;

use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use App\Models\Master\MasterPersonalAccessToken;
use Illuminate\Foundation\Auth\User as Authenticatable;

class MasterUser extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'users';  
    protected $connection = 'master';
    public $incrementing = false;
    protected $keyType = 'string';

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
        ];
    }
    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_roles', 'user_id', 'role_id');
    }
    public function tokens()
    {
        return $this->morphMany(MasterPersonalAccessToken::class, 'tokenable');
    }
}
