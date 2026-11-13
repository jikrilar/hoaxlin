<?php

namespace App\Filament\Support;

use App\Support\CsvCellSanitizer;
use Closure;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streamed CSV export for admin tables (T3.3).
 *
 * Filament's own `ExportAction` needs an `Exporter` class and the `exports` table,
 * which this application does not have. This keeps the existing, already-tested
 * `CsvCellSanitizer` contract (BOM, CRLF, formula neutralisation) and streams the
 * query that the table is currently showing — filters and search included — so
 * the download matches the screen without materialising every row.
 */
final class TableCsvExport
{
    public const CHUNK_SIZE = 500;

    public function __construct(private readonly CsvCellSanitizer $sanitizer) {}

    /**
     * @param  list<string>  $header
     * @param  Closure(Builder): Builder  $query
     * @param  Closure(object): list<int|float|string|null>  $row
     */
    public static function action(string $name, string $label, string $fileName, array $header, Closure $query, Closure $row): Action
    {
        return Action::make($name)
            ->label($label)
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('gray')
            ->authorize(static fn (): bool => auth()->user()?->is_admin === true)
            ->action(static function (Component $livewire) use ($fileName, $header, $query, $row): ?StreamedResponse {
                $base = $livewire->getFilteredTableQuery();

                if ($base === null) {
                    Notification::make()->title('Ekspor tidak dapat disiapkan')->body('Tabel belum siap; muat ulang halaman lalu coba lagi.')->danger()->send();

                    return null;
                }

                return app(self::class)->stream($fileName, $header, $query($base), $row);
            });
    }

    /**
     * @param  list<string>  $header
     * @param  Closure(object): list<int|float|string|null>  $row
     */
    public function stream(string $fileName, array $header, Builder $query, Closure $row): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $query, $row): void {
            $stream = fopen('php://output', 'w');

            fwrite($stream, "\xEF\xBB\xBF");
            $this->writeRow($stream, $header);

            foreach ($query->lazy(self::CHUNK_SIZE) as $record) {
                $this->writeRow($stream, $row($record));
            }

            fclose($stream);
        }, $fileName, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @param  resource  $stream
     * @param  list<int|float|string|null>  $row
     */
    private function writeRow($stream, array $row): void
    {
        $safeRow = array_map(
            fn (int|float|string|null $value): int|float|string => is_string($value)
                ? $this->sanitizer->sanitize($value)
                : ($value ?? ''),
            $row,
        );

        fputcsv($stream, $safeRow, ',', '"', '', "\r\n");
    }
}
