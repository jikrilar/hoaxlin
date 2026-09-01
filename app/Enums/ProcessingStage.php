<?php

namespace App\Enums;

enum ProcessingStage: string
{
    case Queued = 'queued';
    case Extracting = 'extracting';
    case Translating = 'translating';
    case Classifying = 'classifying';
    case Explaining = 'explaining';
    case Done = 'done';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Dalam antrean',
            self::Extracting => 'Ekstraksi teks',
            self::Translating => 'Deteksi bahasa & terjemahan',
            self::Classifying => 'Klasifikasi BERT',
            self::Explaining => 'Penyusunan penjelasan',
            self::Done => 'Selesai',
        };
    }

    /**
     * Drives the Livewire progress indicator without exposing raw stage names.
     */
    public function progressPercentage(): int
    {
        return match ($this) {
            self::Queued => 0,
            self::Extracting => 25,
            self::Translating => 45,
            self::Classifying => 60,
            self::Explaining => 85,
            self::Done => 100,
        };
    }

    public function next(): ?self
    {
        return match ($this) {
            self::Queued => self::Extracting,
            self::Extracting => self::Translating,
            self::Translating => self::Classifying,
            self::Classifying => self::Explaining,
            self::Explaining => self::Done,
            self::Done => null,
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
