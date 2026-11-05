<?php

namespace App\Modules\Identity\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'purpose', 'code_hash', 'failed_attempts', 'expires_at'])]
#[Hidden(['code_hash'])]
class EmailCode extends Model
{
    public const MAX_FAILED_ATTEMPTS = 5;

    public const VALID_MINUTES = 15;

    protected function casts(): array
    {
        return [
            'purpose' => EmailCodePurpose::class,
            'failed_attempts' => 'integer',
            'expires_at' => 'datetime',
        ];
    }
}
