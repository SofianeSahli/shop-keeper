<?php

namespace App\Modules\Contacts\Support;

final readonly class ContactChanges
{
    /**
     * @param  array<string, string|bool|null>  $otherFields
     */
    public function __construct(
        public ?string $name,
        public array $otherFields,
    ) {}
}
