<?php

namespace App\Enums;

enum DetectionLabel: string
{
    case Valid = 'valid';
    case Hoax = 'hoax';
    case Meragukan = 'meragukan';

    public function label(): string
    {
        return match ($this) {
            self::Valid => 'Valid',
            self::Hoax => 'Hoax',
            self::Meragukan => 'Meragukan',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Valid => '✅',
            self::Hoax => '🚨',
            self::Meragukan => '⚠️',
        };
    }

    /**
     * Resolve the reported label from the winning class and its confidence.
     * A low-confidence prediction is surfaced as "meragukan" rather than an
     * overstated verdict, matching the PRD's probabilistic-indication rule.
     */
    public static function fromConfidence(self $predicted, float $confidence, float $threshold): self
    {
        return $confidence < $threshold ? self::Meragukan : $predicted;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            fn (array $options, self $case): array => $options + [$case->value => $case->label()],
            [],
        );
    }
}
