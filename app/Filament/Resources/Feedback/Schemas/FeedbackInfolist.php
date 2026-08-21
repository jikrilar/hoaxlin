<?php

namespace App\Filament\Resources\Feedback\Schemas;

use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;

class FeedbackInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Text::make('submission_id')->label('Submission'),
                Text::make('user.name')->label('Pengguna'),
                Text::make('is_correct')->label('Prediksi benar')->formatStateUsing(fn (bool $state): string => $state ? 'Benar' : 'Tidak benar'),
                Text::make('comment')->label('Komentar')->placeholder('-')->columnSpanFull(),
                Text::make('created_at')->label('Dikirim')->dateTime(),
            ]);
    }
}
