<?php

namespace App\Modules\Catalog\Support;

final readonly class CloseScore
{
    public const MAX_LETTER_DIFFERENCE = 2;

    public const MIN_SHARED_LENGTH = 3;

    private function __construct(
        public int $distance,
        public bool $contains,
        public int $sharedWords,
    ) {}

    public static function between(string $comparedName, string $comparedCandidate): ?self
    {
        $score = new self(
            CodePointDistance::between($comparedName, $comparedCandidate),
            self::oneContainsTheOther($comparedName, $comparedCandidate),
            count(array_intersect(self::significantWords($comparedName), self::significantWords($comparedCandidate))),
        );

        return $score->isClose() ? $score : null;
    }

    /**
     * @return list<string>
     */
    public static function significantWords(string $comparedName): array
    {
        return array_values(array_unique(array_filter(
            NameComparison::words($comparedName),
            fn (string $word): bool => mb_strlen($word) >= self::MIN_SHARED_LENGTH,
        )));
    }

    public function compare(self $other): int
    {
        return [$this->distance, ! $this->contains, -$this->sharedWords]
            <=> [$other->distance, ! $other->contains, -$other->sharedWords];
    }

    private static function oneContainsTheOther(string $first, string $second): bool
    {
        [$shorter, $longer] = mb_strlen($first) <= mb_strlen($second) ? [$first, $second] : [$second, $first];

        return mb_strlen($shorter) >= self::MIN_SHARED_LENGTH && str_contains($longer, $shorter);
    }

    private function isClose(): bool
    {
        return $this->distance <= self::MAX_LETTER_DIFFERENCE || $this->contains || $this->sharedWords > 0;
    }
}
