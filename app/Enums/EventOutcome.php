<?php

namespace App\Enums;

enum EventOutcome: string
{
    case Started = 'started';
    case Succeeded = 'succeeded';
    case Retried = 'retried';
    case Degraded = 'degraded';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Started => 'Dimulai',
            self::Succeeded => 'Berhasil',
            self::Retried => 'Dicoba ulang',
            self::Degraded => 'Berjalan terbatas',
            self::Failed => 'Gagal',
        };
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
