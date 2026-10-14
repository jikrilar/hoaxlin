<?php

namespace App\Filament\Resources\Datasets\Tables;

use App\Filament\Resources\Datasets\DatasetResource;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DatasetsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('text')->label('Cuplikan teks')->limit(70)->searchable()->wrap(),
                TextColumn::make('label')->label('Label katalog')->formatStateUsing(DatasetResource::labelText(...))->sortable(),
                TextColumn::make('source')->label('Sumber')->limit(80)->searchable()->sortable()->placeholder('Sumber belum tercatat')->wrap(),
                TextColumn::make('verifier.name')->label('Diverifikasi oleh')->placeholder('Belum diverifikasi')->wrap(),
                TextColumn::make('created_at')->label('Dibuat')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('label')->label('Label katalog')->options(DatasetResource::labelOptions()),
                SelectFilter::make('verified_by')
                    ->label('Verifikasi')
                    ->relationship(
                        'verifier',
                        'name',
                        modifyQueryUsing: fn (Builder $query): Builder => $query->admins(),
                    ),
            ])
            ->recordActions([
                ViewAction::make()->label('Lihat teks lengkap'),
                EditAction::make()->label('Ubah'),
            ])
            ->toolbarActions([
                DeleteBulkAction::make()
                    ->label('Hapus data kurasi terpilih')
                    ->modalHeading('Hapus data kurasi terpilih?')
                    ->modalDescription(fn (DeleteBulkAction $action): string => 'Anda memilih '.$action->getSelectedRecordsQuery()->count().' data kurasi. Data akan dihapus dari katalog. Tindakan ini tidak dapat dibatalkan dan tidak melatih ulang atau mengganti model BERT aktif.')
                    ->modalSubmitActionLabel('Hapus data kurasi terpilih'),
            ])
            ->emptyStateHeading('Tidak ada data kurasi yang ditampilkan')
            ->emptyStateDescription('Tambahkan data kurasi atau ubah pencarian dan filter untuk melihat referensi lain.');
    }
}
