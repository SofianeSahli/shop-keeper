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
        $trimmedName = trim($name);
        $decomposed = Normalizer::normalize($trimmedName, Normalizer::FORM_KD);

        if ($decomposed === false) {
            return '';
        }

        $withoutMarks = preg_replace('/\p{Mn}+/u', '', $decomposed);
        $withoutVariants = strtr($withoutMarks, self::LETTER_VARIANTS);
        $lowercase = mb_strtolower($withoutVariants);
        $words = preg_split('/[^\p{L}\p{N}]+/u', $lowercase, -1, PREG_SPLIT_NO_EMPTY);
        $singularWords = array_map(self::withoutPluralMark(...), $words);

        return implode(self::WORD_SEPARATOR, $singularWords);
    }

    public static function identityKey(string $name): string
    {
        $compared = self::compared($name);

        return str_replace(self::WORD_SEPARATOR, '', $compared);
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
