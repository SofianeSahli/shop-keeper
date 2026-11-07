<?php

namespace App\Modules\Catalog\Support;

use App\Modules\Catalog\Models\CatalogLanguage;
use App\Modules\Catalog\Models\GlobalProduct;
use App\Modules\Catalog\Models\GlobalProductName;

final readonly class CatalogFileEntry
{
    /**
     * @param  array<string, string>  $names
     * @param  array<string, list<string>>  $spellings
     */
    private function __construct(
        public string $key,
        public ?Barcode $barcode,
        public array $names,
        public array $spellings,
    ) {}

    public static function fromArray(mixed $entry): self
    {
        if (! is_array($entry)) {
            throw new CatalogFileError('entry.invalid');
        }

        return new self(
            self::key($entry['key'] ?? null),
            self::barcode($entry['barcode'] ?? null),
            self::names($entry['names'] ?? null),
            self::spellings($entry['spellings'] ?? []),
        );
    }

    public static function keyOf(mixed $entry): ?string
    {
        return is_array($entry) && is_string($entry['key'] ?? null) ? $entry['key'] : null;
    }

    private static function key(mixed $key): string
    {
        if (! is_string($key)
            || strlen($key) > GlobalProduct::KEY_MAX_LENGTH
            || preg_match(GlobalProduct::KEY_PATTERN, $key) !== 1) {
            throw new CatalogFileError('key.invalid');
        }

        return $key;
    }

    private static function barcode(mixed $barcode): ?Barcode
    {
        if ($barcode === null) {
            return null;
        }

        if (! is_string($barcode)) {
            throw new CatalogFileError(InvalidBarcode::MALFORMED);
        }

        try {
            return Barcode::parse($barcode);
        } catch (InvalidBarcode $failure) {
            throw new CatalogFileError($failure->fieldCode);
        }
    }

    private static function names(mixed $names): array
    {
        if (! is_array($names)) {
            throw new CatalogFileError('name.missing');
        }

        $trimmed = [];

        foreach (CatalogLanguage::cases() as $language) {
            $trimmed[$language->value] = self::text($names[$language->value] ?? null, 'name');
        }

        return $trimmed;
    }

    private static function spellings(mixed $spellings): array
    {
        if (! is_array($spellings)) {
            throw new CatalogFileError('spelling.invalid');
        }

        $trimmed = [];

        foreach (CatalogLanguage::cases() as $language) {
            $ofLanguage = $spellings[$language->value] ?? [];

            if (! is_array($ofLanguage) || ! array_is_list($ofLanguage)) {
                throw new CatalogFileError('spelling.invalid');
            }

            $trimmed[$language->value] = array_map(fn (mixed $spelling): string => self::text($spelling, 'spelling'), $ofLanguage);
        }

        return $trimmed;
    }

    private static function text(mixed $text, string $field): string
    {
        if (! is_string($text) || trim($text) === '') {
            throw new CatalogFileError("{$field}.missing");
        }

        if (mb_strlen(trim($text)) > GlobalProductName::NAME_MAX_LENGTH) {
            throw new CatalogFileError("{$field}.too_long");
        }

        return trim($text);
    }
}
