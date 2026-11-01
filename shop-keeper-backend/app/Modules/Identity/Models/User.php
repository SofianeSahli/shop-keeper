<?php

namespace App\Modules\Identity\Models;

use App\Modules\Identity\database\factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'name',
    'email',
    'email_verified_at',
    'password',
    'google_id',
    'phone',
    'terms_version',
    'terms_accepted_at',
    'pin_hash',
])]
#[Hidden(['password', 'pin_hash', 'remember_token'])]
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    public function hasConfirmedEmail(): bool
    {
        return $this->email_verified_at !== null;
    }

    public function hasPin(): bool
    {
        return $this->pin_hash !== null;
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'terms_accepted_at' => 'datetime',
            'pin_failed_attempts' => 'integer',
            'pin_locked_until' => 'datetime',
            'password_failed_attempts' => 'integer',
            'password_locked_until' => 'datetime',
        ];
    }
}
