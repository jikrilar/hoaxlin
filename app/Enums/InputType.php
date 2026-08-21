<?php

namespace App\Enums;

enum InputType: string
{
    case Text = 'text';
    case Image = 'image';
    case Video = 'video';
    case Url = 'url';

    /**
     * Normalize a request input type, collapsing the UI-only "video_url"
     * variant onto the persisted video type.
     */
    public static function fromRequest(string $value): self
    {
        return self::from($value === 'video_url' ? 'video' : $value);
    }

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Teks',
            self::Image => 'Gambar',
            self::Video => 'Video',
            self::Url => 'URL',
        };
    }

    /**
     * Media extraction is slow and billable, so those submissions are routed
     * to a dedicated queue that cannot starve fast text submissions.
     */
    public function requiresMediaQueue(): bool
    {
        return in_array($this, [self::Image, self::Video], true);
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
