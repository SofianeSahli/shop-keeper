<?php

namespace App\Modules\Identity\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'device_id', 'token_hash', 'code_hash', 'failed_attempts', 'expires_at'])]
#[Hidden(['token_hash', 'code_hash'])]
class PendingSignIn extends Model
{
    public const MAX_FAILED_ATTEMPTS = 5;

    public const VALID_MINUTES = 10;

    public const VALID_SECONDS = self::VALID_MINUTES * 60;

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'failed_attempts' => 'integer',
            'expires_at' => 'datetime',
        ];
    }
}
