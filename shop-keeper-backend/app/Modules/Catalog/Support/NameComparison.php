<?php

namespace App\Modules\Catalog\Support;

use Normalizer;

final class NameComparison
{
    private const LETTER_VARIANTS = [
        'ة' => 'ه',
        'ى' => 'ي',
        'ٱ' => 'ا',
        'ـ' => '',
        'œ' => 'oe',
        'Œ' => 'oe',
        'æ' => 'ae',
        'Æ' => 'ae',
    ];

    private const WORD_SEPARATOR = ' ';

    public static function compared(string $name): string
    {
        $decomposed = Normalizer::normalize(trim($name), Normalizer::FORM_KD);

        if ($decomposed === false) {
            return '';
        }

        $withoutMarks = preg_replace('/\p{Mn}+/u', '', $decomposed);
        $lowercase = mb_strtolower(strtr($withoutMarks, self::LETTER_VARIANTS));
        $words = preg_split('/[^\p{L}\p{N}]+/u', $lowercase, -1, PREG_SPLIT_NO_EMPTY);

        return implode(self::WORD_SEPARATOR, array_map(self::withoutPluralMark(...), $words));
    }

    public static function identityKey(string $name): string
    {
        return str_replace(self::WORD_SEPARATOR, '', self::compared($name));
    }

    public static function words(string $comparedName): array
    {
        return $comparedName === '' ? [] : explode(self::WORD_SEPARATOR, $comparedName);
    }

    private static function withoutPluralMark(string $word): string
    {
        if (mb_strlen($word) < 2 || preg_match('/^\p{Latin}+$/u', $word) !== 1) {
            return $word;
        }

        return preg_replace('/[sx]$/u', '', $word);
    }
}
