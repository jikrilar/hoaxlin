<?php

namespace Tests\Feature;

use App\Models\DetectionResult;
use App\Models\Submission;
use App\Models\User;
use App\Services\Export\SubmissionCsvExporter;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

class CsvExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_csv_neutralizes_formula_values_from_text_and_urls(): void
    {
        $user = User::factory()->create();
        $values = [
            '=1+1',
            '+SUM(A1:A2)',
            '-2+3',
            '@SUM(1,1)',
            "\t=cmd|' /C calc'!A0",
            "\r=1+1",
            "\n=1+1",
        ];

        $submissions = collect($values)->mapWithKeys(function (string $value) use ($user): array {
            $submission = Submission::factory()->create([
                'user_id' => $user->id,
                'raw_input' => $value,
            ]);

            return [$submission->id => $value];
        });

        $urlSubmission = Submission::factory()->create([
            'user_id' => $user->id,
            'input_type' => 'url',
            'raw_input' => null,
            'source_url' => '=HYPERLINK("https://evil.test","klik")',
        ]);

        $rows = $this->rowsById($this->download($user));

        foreach ($submissions as $id => $value) {
            $this->assertSame("'{$value}", $rows[$id][10]);
        }

        $this->assertSame("'=HYPERLINK(\"https://evil.test\",\"klik\")", $rows[$urlSubmission->id][10]);
    }

    public function test_csv_preserves_normal_quoted_multiline_and_unicode_text(): void
    {
        $user = User::factory()->create();
        $text = "Berita, \"khusus\"\nBaris kedua berbahasa Indonesia — tetap utuh.";
        $submission = Submission::factory()->create([
            'user_id' => $user->id,
            'raw_input' => $text,
        ]);

        $content = $this->download($user);
        $rows = $this->rowsById($content);

        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertSame($text, $rows[$submission->id][10]);
        $this->assertSame([
            'ID', 'Tipe', 'Status', 'Label', 'Confidence', 'Model',
            'Bahasa Sumber', 'Provider Terjemahan', 'Model Terjemahan',
            'Dibuat', 'Teks/URL',
        ], $this->parseCsv($content)[0]);
    }

    public function test_csv_is_strictly_scoped_to_the_authenticated_user_even_for_admins(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $otherUser = User::factory()->create();
        $owned = Submission::factory()->create([
            'user_id' => $admin->id,
            'raw_input' => 'Baris milik admin sendiri',
        ]);
        $foreign = Submission::factory()->create([
            'user_id' => $otherUser->id,
            'raw_input' => 'Rahasia pengguna lain',
        ]);

        $rows = $this->rowsById($this->download($admin));

        $this->assertArrayHasKey($owned->id, $rows);
        $this->assertArrayNotHasKey($foreign->id, $rows);
    }

    public function test_guest_cannot_export_csv(): void
    {
        $this->get(route('riwayat.csv'))
            ->assertRedirect(route('login'));
    }

    public function test_only_completed_submissions_export_detection_result_as_final(): void
    {
        $user = User::factory()->create();
        $processing = $this->submissionWithResult($user, 'processing', 'hoax');
        $failed = $this->submissionWithResult($user, 'failed', 'valid');
        $completed = $this->submissionWithResult($user, 'completed', 'meragukan');

        $rows = $this->rowsById($this->download($user));

        foreach ([$processing, $failed] as $partial) {
            $this->assertSame('-', $rows[$partial->id][3]);
            $this->assertSame('-', $rows[$partial->id][4]);
            $this->assertSame('-', $rows[$partial->id][5]);
        }

        $this->assertSame('meragukan', $rows[$completed->id][3]);
        $this->assertSame('0.8123', $rows[$completed->id][4]);
        $this->assertSame('indobert-export-test', $rows[$completed->id][5]);
    }

    public function test_csv_includes_translation_provenance_without_internal_accounting(): void
    {
        $user = User::factory()->create();
        $translated = Submission::factory()->create([
            'user_id' => $user->id,
            'status' => 'completed',
            'source_language' => 'en',
            'translated_text' => 'Berita yang telah diterjemahkan.',
            'translation_provider' => 'openai',
            'translation_model' => 'gpt-4o-mini',
            'translation_cached' => false,
            'translation_input_tokens' => 123,
            'translation_output_tokens' => 45,
            'translation_estimated_cost_usd' => 0.012345,
        ]);
        $untranslated = Submission::factory()->create([
            'user_id' => $user->id,
            'status' => 'completed',
            'source_language' => 'id',
        ]);

        $content = $this->download($user);
        $rows = $this->rowsById($content);
        $header = implode(',', $this->parseCsv($content)[0]);

        $this->assertSame('en', $rows[$translated->id][6]);
        $this->assertSame('openai', $rows[$translated->id][7]);
        $this->assertSame('gpt-4o-mini', $rows[$translated->id][8]);
        $this->assertSame('id', $rows[$untranslated->id][6]);
        $this->assertSame('-', $rows[$untranslated->id][7]);
        $this->assertSame('-', $rows[$untranslated->id][8]);
        $this->assertStringNotContainsString('translation_input_tokens', $header);
        $this->assertStringNotContainsString('translation_output_tokens', $header);
        $this->assertStringNotContainsString('translation_estimated_cost_usd', $header);
        $this->assertStringNotContainsString('123', $content);
        $this->assertStringNotContainsString('0.012345', $content);
    }

    public function test_large_export_is_chunked_and_eager_loads_results_per_chunk(): void
    {
        $user = User::factory()->create();
        $count = (SubmissionCsvExporter::CHUNK_SIZE * 2) + 5;

        Submission::factory()->count($count)->create([
            'user_id' => $user->id,
            'status' => 'completed',
        ])->each(function (Submission $submission): void {
            DetectionResult::factory()->create(['submission_id' => $submission->id]);
        });

        $submissionQueries = 0;
        $resultQueries = 0;

        DB::listen(function (QueryExecuted $query) use (&$submissionQueries, &$resultQueries): void {
            $sql = strtolower($query->sql);

            if (str_starts_with(ltrim($sql), 'select') && str_contains($sql, 'from "submissions"')) {
                $submissionQueries++;
            }

            if (str_starts_with(ltrim($sql), 'select') && str_contains($sql, 'from "detection_results"')) {
                $resultQueries++;
            }
        });

        $rows = $this->parseCsv($this->download($user));

        $this->assertCount($count + 1, $rows);
        $this->assertSame(3, $submissionQueries, 'Submission export must read records in bounded chunks.');
        $this->assertSame(3, $resultQueries, 'Detection results must be eager-loaded once per chunk, not once per row.');
    }

    private function submissionWithResult(User $user, string $status, string $label): Submission
    {
        $submission = Submission::factory()->create([
            'user_id' => $user->id,
            'status' => $status,
        ]);
        DetectionResult::factory()->create([
            'submission_id' => $submission->id,
            'label' => $label,
            'confidence_score' => 0.8123,
            'model_version' => 'indobert-export-test',
        ]);

        return $submission;
    }

    private function download(User $user): string
    {
        $response = $this->actingAs($user)->get(route('riwayat.csv'));

        $response->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $baseResponse = $response->baseResponse;
        $this->assertInstanceOf(StreamedResponse::class, $baseResponse);

        return $response->streamedContent();
    }

    /**
     * @return array<int, list<string|null>>
     */
    private function rowsById(string $content): array
    {
        $rows = $this->parseCsv($content);
        array_shift($rows);

        $indexed = [];
        foreach ($rows as $row) {
            $indexed[(int) $row[0]] = $row;
        }

        return $indexed;
    }

    /**
     * @return list<list<string|null>>
     */
    private function parseCsv(string $content): array
    {
        $stream = fopen('php://temp', 'w+');
        fwrite($stream, substr($content, 3));
        rewind($stream);

        $rows = [];
        while (($row = fgetcsv($stream, escape: '')) !== false) {
            $rows[] = $row;
        }

        fclose($stream);

        return $rows;
    }
}
