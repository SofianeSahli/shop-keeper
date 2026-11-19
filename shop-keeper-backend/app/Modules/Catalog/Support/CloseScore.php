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
        $distance = CodePointDistance::between($comparedName, $comparedCandidate);
        $contains = self::oneContainsTheOther($comparedName, $comparedCandidate);
        $nameWords = self::significantWords($comparedName);
        $candidateWords = self::significantWords($comparedCandidate);
        $sharedWords = array_intersect($nameWords, $candidateWords);
        $sharedWordCount = count($sharedWords);
        $score = new self($distance, $contains, $sharedWordCount);

        return $score->isClose() ? $score : null;
    }

    /**
     * @return list<string>
     */
    public static function significantWords(string $comparedName): array
    {
        $words = NameComparison::words($comparedName);
        $longWords = array_filter($words, fn (string $word): bool => mb_strlen($word) >= self::MIN_SHARED_LENGTH);
        $uniqueWords = array_unique($longWords);

        return array_values($uniqueWords);
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
