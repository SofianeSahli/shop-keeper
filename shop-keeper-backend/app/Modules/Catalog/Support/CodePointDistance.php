<?php

namespace App\Modules\Catalog\Support;

final class CodePointDistance
{
    public static function between(string $first, string $second): int
    {
        $firstCharacters = mb_str_split($first);
        $secondCharacters = mb_str_split($second);
        $previousRow = range(0, count($secondCharacters));

        foreach ($firstCharacters as $firstIndex => $firstCharacter) {
            $currentRow = [$firstIndex + 1];

            foreach ($secondCharacters as $secondIndex => $secondCharacter) {
                $currentRow[] = min(
                    $previousRow[$secondIndex + 1] + 1,
                    $currentRow[$secondIndex] + 1,
                    $previousRow[$secondIndex] + ($firstCharacter === $secondCharacter ? 0 : 1),
                );
            }

            $previousRow = $currentRow;
        }

        return $previousRow[count($secondCharacters)];
    }
}
