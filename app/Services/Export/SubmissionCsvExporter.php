<?php

namespace App\Services\Export;

use App\Models\Submission;
use App\Models\User;
use App\Support\CsvCellSanitizer;
use Illuminate\Database\Eloquent\Collection;

final class SubmissionCsvExporter
{
    public const CHUNK_SIZE = 100;

    private const HEADER = [
        'ID',
        'Tipe',
        'Status',
        'Label',
        'Confidence',
        'Model',
        'Dibuat',
        'Teks/URL',
    ];

    public function __construct(private readonly CsvCellSanitizer $sanitizer) {}

    /**
     * @param  resource  $stream
     */
    public function write(User $user, $stream): void
    {
        fwrite($stream, "\xEF\xBB\xBF");
        $this->writeRow($stream, self::HEADER);

        Submission::query()
            ->select([
                'id',
                'user_id',
                'input_type',
                'status',
                'raw_input',
                'source_url',
                'created_at',
            ])
            ->with(['detectionResult:id,submission_id,label,confidence_score,model_version'])
            ->forUser($user)
            ->chunkByIdDesc(self::CHUNK_SIZE, function (Collection $submissions) use ($stream): void {
                foreach ($submissions as $submission) {
                    $result = $submission->isCompleted()
                        ? $submission->detectionResult
                        : null;

                    $this->writeRow($stream, [
                        $submission->id,
                        $submission->input_type,
                        $submission->status,
                        $result?->label ?? '-',
                        $result?->confidence_score ?? '-',
                        $result?->model_version ?? '-',
                        $submission->created_at?->format('Y-m-d H:i') ?? '',
                        mb_substr($submission->raw_input ?? $submission->source_url ?? '', 0, 200),
                    ]);
                }
            });
    }

    /**
     * @param  resource  $stream
     * @param  list<int|float|string>  $row
     */
    private function writeRow($stream, array $row): void
    {
        $safeRow = array_map(
            fn (int|float|string $value): int|float|string => is_string($value) && $value !== '-'
                ? $this->sanitizer->sanitize($value)
                : $value,
            $row,
        );

        fputcsv($stream, $safeRow, ',', '"', '', "\r\n");
    }
}
