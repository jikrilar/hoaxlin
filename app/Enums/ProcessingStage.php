<?php

namespace App\Enums;

enum ProcessingStage: string
{
    case Queued = 'queued';
    case Extracting = 'extracting';
    case Translating = 'translating';
    case Classifying = 'classifying';
    case Retrieving = 'retrieving';
    case Explaining = 'explaining';
    case Done = 'done';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Menunggu giliran',
            self::Extracting => 'Membaca teks dari input',
            self::Translating => 'Menerjemahkan teks',
            self::Classifying => 'Menganalisis pola bahasa',
            self::Retrieving => 'Mencari rujukan terkait',
            self::Explaining => 'Menyusun penjelasan',
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
            self::Retrieving => 75,
            self::Explaining => 85,
            self::Done => 100,
        };
    }

    /**
     * Human-facing checklist for the progress stepper (T2.6).
     *
     * The translation step is always listed because the pipeline description is
     * the same for every input type; it is only marked "skipped" once the data
     * proves the submission moved past it without one.
     *
     * @return list<array{key: string, label: string, stage: self}>
     */
    public static function steps(): array
    {
        return [
            ['key' => 'extracting', 'label' => 'Ekstraksi', 'stage' => self::Extracting],
            ['key' => 'translating', 'label' => 'Terjemahan', 'stage' => self::Translating],
            ['key' => 'classifying', 'label' => 'Analisis', 'stage' => self::Classifying],
            ['key' => 'retrieving', 'label' => 'Rujukan', 'stage' => self::Retrieving],
            ['key' => 'explaining', 'label' => 'Penjelasan', 'stage' => self::Explaining],
        ];
    }

    /**
     * Stepper status for each step, derived only from the recorded stage.
     *
     * @return list<array{key: string, label: string, status: string}>
     */
    public static function stepStates(?self $current, bool $completed = false, bool $failed = false): array
    {
        $currentIndex = $current === null ? null : array_search(
            $current,
            array_map(fn (array $step): self => $step['stage'], self::steps()),
            true,
        );
        if ($current === self::Queued) {
            $currentIndex = null;
        }

        $states = [];
        foreach (self::steps() as $index => $step) {
            $status = 'todo';
            if ($completed) {
                $status = 'done';
            } elseif ($failed) {
                // The recorded stage is the one that failed; later steps were never reached.
                $status = $index === $currentIndex && $current !== null
                    ? 'error'
                    : ($currentIndex !== null && $index < $currentIndex ? 'done' : 'todo');
            } elseif ($currentIndex !== null) {
                $status = $index < $currentIndex ? 'done' : ($index === $currentIndex ? 'current' : 'todo');
            }
            $states[] = ['key' => $step['key'], 'label' => $step['label'], 'status' => $status];
        }

        return $states;
    }

    public function next(): ?self
    {
        return match ($this) {
            self::Queued => self::Extracting,
            self::Extracting => self::Translating,
            self::Translating => self::Classifying,
            self::Classifying => self::Retrieving,
            self::Retrieving => self::Explaining,
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
