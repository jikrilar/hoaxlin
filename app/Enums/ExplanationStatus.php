<?php

namespace App\Enums;

enum ExplanationStatus: string
{
    case Pending = 'pending';
    case Ready = 'ready';
    case Unavailable = 'unavailable';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Sedang disusun',
            self::Ready => 'Tersedia',
            self::Unavailable => 'Tidak tersedia',
        };
    }

    /**
     * The BERT verdict stands on its own, so a missing narrative is a degraded
     * result rather than a failed submission.
     */
    public function isDegraded(): bool
    {
        return $this === self::Unavailable;
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
