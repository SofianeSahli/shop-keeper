<?php

namespace App\Modules\Identity\Google;

use Illuminate\Support\Str;

final readonly class GoogleIdentity
{
    public function __construct(
        public string $sub,
        public string $email,
        public ?string $name,
        public ?string $hostedDomain = null,
    ) {}

    public function isAuthoritativeForEmail(): bool
    {
        return str_ends_with($this->email, '@gmail.com') || $this->hostedDomain !== null;
    }

    public function nameForNewAccount(): string
    {
        return $this->name ?? Str::before($this->email, '@');
    }
}
