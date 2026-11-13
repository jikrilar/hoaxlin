<?php

namespace Tests\Feature;

use App\Filament\Resources\AdminLogs\Pages\ListAdminLogs;
use App\Filament\Resources\Datasets\Pages\ListDatasets;
use App\Filament\Resources\Feedback\Pages\ListFeedback;
use App\Filament\Resources\ModelVersions\Pages\ListModelVersions;
use App\Filament\Resources\Submissions\Pages\ListSubmissions;
use App\Filament\Resources\Submissions\SubmissionResource;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\AdminLog;
use App\Models\Dataset;
use App\Models\DetectionResult;
use App\Models\Feedback;
use App\Models\ModelVersion;
use App\Models\Submission;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** T3.3: every admin table shows a short default view, badges, sticky filters and CSV. */
class AdminTableStandardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Http::preventStrayRequests();
    }

    #[DataProvider('tables')]
    public function test_default_columns_are_at_most_six_and_technical_columns_start_hidden(string $list, string $model, array $technical): void
    {
        $this->admin();
        $this->factoryFor($model)->create();

        $table = $this->table($list);

        $this->assertLessThanOrEqual(6, count($table->getVisibleColumns()), "{$list} shows at most six columns by default");
        foreach ($technical as $column) {
            $this->assertTrue($table->getColumn($column)->isToggleable(), "{$column} can be toggled back on");
            $this->assertTrue($table->getColumn($column)->isToggledHiddenByDefault(), "{$column} starts hidden");
            $this->assertNotContains($column, array_keys($table->getVisibleColumns()), "{$column} is not in the default view");
        }
    }

    public static function tables(): array
    {
        return [
            'submissions' => [ListSubmissions::class, Submission::class, ['id']],
            'feedback' => [ListFeedback::class, Feedback::class, ['submission_id']],
            'users' => [ListUsers::class, User::class, []],
            'logs' => [ListAdminLogs::class, AdminLog::class, ['id', 'target_id']],
            'datasets' => [ListDatasets::class, Dataset::class, ['source']],
            // T3.8 supersedes T3.3 here: "Dibanding baseline" is a default column now,
            // and only these three stay behind the toggle.
            'model versions' => [ListModelVersions::class, ModelVersion::class, ['total_rows', 'f1_score', 'training_duration']],
        ];
    }

    #[DataProvider('tables')]
    public function test_filters_sit_above_the_table_and_survive_a_reload_in_the_session(string $list, string $model, array $technical): void
    {
        $this->admin();

        $table = $this->table($list);
        $this->assertSame(FiltersLayout::AboveContent, $table->getFiltersLayout(), "{$list} puts filters above the table");
        $this->assertTrue($table->persistsFiltersInSession(), "{$list} keeps filters in the session");
        $this->assertNotNull($table->getDefaultSortColumn(), "{$list} has an explicit default order");
        $this->assertSame('desc', $table->getDefaultSortDirection());
    }

    public function test_saved_filters_are_restored_after_remounting_the_page(): void
    {
        $this->admin();
        $matching = Submission::factory()->create(['status' => 'failed']);
        $other = Submission::factory()->create(['status' => 'completed']);

        Livewire::test(ListSubmissions::class)->filterTable('status', 'failed')
            ->assertCanSeeTableRecords([$matching])->assertCanNotSeeTableRecords([$other]);

        // A fresh mount reads the same session key, as a page reload would.
        Livewire::test(ListSubmissions::class)
            ->assertCanSeeTableRecords([$matching])->assertCanNotSeeTableRecords([$other]);
    }

    #[DataProvider('badgeColumns')]
    public function test_status_and_label_columns_are_badges_with_a_colour_and_an_icon(string $list, string $model, string $column, string $value, string $expectedColor, string $expectedIcon): void
    {
        $this->admin();
        $record = $this->badgeRecord($model, $value);

        $page = Livewire::test($list)->assertCanSeeTableRecords([$record]);
        $tableColumn = $page->instance()->getTable()->getColumn($column);

        $this->assertTrue($tableColumn->isBadge(), "{$column} renders as a badge");
        $tableColumn->record($record);
        $this->assertSame($expectedColor, $tableColumn->getColor($tableColumn->getState()), "{$column} uses the {$expectedColor} tone");
        $this->assertSame($expectedIcon, $tableColumn->getIcon($tableColumn->getState())?->value, "{$column} carries an icon, not colour alone");
    }

    public static function badgeColumns(): array
    {
        return [
            'submission failed' => [ListSubmissions::class, Submission::class, 'status', 'failed', 'danger', 'o-x-circle'],
            'submission completed' => [ListSubmissions::class, Submission::class, 'status', 'completed', 'success', 'o-check-circle'],
            'feedback correct' => [ListFeedback::class, Feedback::class, 'is_correct', 'correct', 'success', 'o-check-circle'],
            'feedback incorrect' => [ListFeedback::class, Feedback::class, 'is_correct', 'incorrect', 'danger', 'o-x-circle'],
            'log delete' => [ListAdminLogs::class, AdminLog::class, 'action', 'delete', 'danger', 'o-trash'],
            'dataset hoax' => [ListDatasets::class, Dataset::class, 'label', 'hoax', 'danger', 'o-x-circle'],
            'dataset meragukan' => [ListDatasets::class, Dataset::class, 'label', 'meragukan', 'warning', 'o-question-mark-circle'],
            'model version failed' => [ListModelVersions::class, ModelVersion::class, 'status', 'failed', 'danger', 'o-x-circle'],
        ];
    }

    public function test_submission_label_column_never_promotes_a_partial_record_to_a_result(): void
    {
        $this->admin();
        $pending = Submission::factory()->create(['status' => 'processing']);
        DetectionResult::factory()->for($pending)->create(['label' => 'hoax']);

        $page = Livewire::test(ListSubmissions::class);
        $column = $page->instance()->getTable()->getColumn('detectionResult.label');
        $column->record($pending);

        $this->assertSame('gray', $column->getColor($column->getState()), 'a partial record is neutral, never the hoax tone');
        $this->assertSame('o-clock', $column->getIcon($column->getState())?->value);
    }

    public function test_submission_export_streams_filtered_metadata_without_user_content(): void
    {
        $this->admin();
        $failed = Submission::factory()->create(['status' => 'failed', 'raw_input' => 'PRIVATE_EXPORT_INPUT', 'source_url' => null]);
        $other = Submission::factory()->create(['status' => 'failed', 'raw_input' => 'Teks biasa']);
        Submission::factory()->create(['status' => 'completed', 'raw_input' => 'Bukan bagian ekspor']);

        $page = Livewire::test(ListSubmissions::class)->filterTable('status', 'failed')
            ->callTableAction('exportCsv')
            ->assertFileDownloaded();

        $csv = $this->downloadedCsv($page);
        $rows = $this->parseCsv($csv);

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertSame(['ID', 'Jenis input', 'Status', 'Dikirim', 'Tahap akhir', 'Kode kendala'], $rows[0]);
        $ids = array_column(array_slice($rows, 1), 0);
        $this->assertEqualsCanonicalizing([(string) $failed->id, (string) $other->id], $ids, 'only the filtered rows are exported');
        $this->assertStringNotContainsString('Bukan bagian ekspor', $csv);
        $this->assertStringNotContainsString('PRIVATE_EXPORT_INPUT', $csv, 'submission content never reaches the admin export');
        $this->assertStringNotContainsString('Teks biasa', $csv);
    }

    public function test_csv_export_never_carries_private_user_content_or_credentials(): void
    {
        $this->admin();
        $user = User::factory()->create([
            'name' => '=HYPERLINK("https://evil.test")',
            'email' => 'private-export@example.test',
            'password' => bcrypt('Fixture-password-123'),
            'remember_token' => 'PRIVATE_REMEMBER_TOKEN',
        ]);

        $csv = $this->downloadedCsv(Livewire::test(ListUsers::class)->callTableAction('exportCsv')->assertFileDownloaded());

        $this->assertStringContainsString('private-export@example.test', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv, 'user-provided names are formula-neutralised');
        $this->assertStringNotContainsString('PRIVATE_REMEMBER_TOKEN', $csv);
        $this->assertStringNotContainsString('$2y$', $csv, 'no password hash is exported');
    }

    public function test_feedback_export_carries_the_assessment_without_credentials(): void
    {
        $this->admin();
        Feedback::factory()->create(['is_correct' => false, 'comment' => '=cmd|calc']);

        $csv = $this->downloadedCsv(Livewire::test(ListFeedback::class)->callTableAction('exportCsv')->assertFileDownloaded());

        $this->assertStringContainsString("'=cmd|calc", $csv);
        $this->assertStringNotContainsString('$2y$', $csv);
    }

    public function test_export_is_admin_only_and_absent_where_it_is_not_offered(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]));
        $this->get(SubmissionResource::getUrl('index'))->assertForbidden();

        $this->admin();
        foreach ([ListSubmissions::class, ListFeedback::class, ListUsers::class] as $list) {
            Livewire::test($list)->assertTableActionExists('exportCsv');
        }
        foreach ([ListAdminLogs::class, ListDatasets::class, ListModelVersions::class] as $list) {
            Livewire::test($list)->assertTableActionDoesNotExist('exportCsv');
        }
    }

    #[DataProvider('emptyStates')]
    public function test_empty_state_explains_what_to_do_next(string $list): void
    {
        $this->admin();

        $table = $this->table($list);

        $this->assertNotEmpty($table->getEmptyStateHeading());
        $this->assertNotEmpty($table->getEmptyStateDescription());
    }

    public static function emptyStates(): array
    {
        return [
            'submissions' => [ListSubmissions::class], 'feedback' => [ListFeedback::class], 'users' => [ListUsers::class],
            'logs' => [ListAdminLogs::class], 'datasets' => [ListDatasets::class], 'model versions' => [ListModelVersions::class],
        ];
    }

    private function table(string $list): Table
    {
        return Livewire::test($list)->instance()->getTable();
    }

    private function factoryFor(string $model): object
    {
        return $model::factory();
    }

    private function badgeRecord(string $model, string $value): object
    {
        return match ($model) {
            Submission::class => Submission::factory()->create(['status' => $value]),
            Feedback::class => Feedback::factory()->create(['is_correct' => $value === 'correct']),
            AdminLog::class => AdminLog::factory()->create(['action' => $value]),
            Dataset::class => Dataset::factory()->create(['label' => $value]),
            ModelVersion::class => ModelVersion::factory()->create(['status' => $value]),
            default => throw new \LogicException('Unsupported badge fixture'),
        };
    }

    private function downloadedCsv(object $page): string
    {
        return base64_decode(data_get($page->effects, 'download.content'));
    }

    /**
     * @return list<list<string>>
     */
    private function parseCsv(string $csv): array
    {
        $handle = fopen('php://memory', 'r+');
        fwrite($handle, substr($csv, 3)); // drop the UTF-8 BOM
        rewind($handle);

        $rows = [];
        while (($row = fgetcsv($handle, escape: '')) !== false) {
            $rows[] = $row;
        }
        fclose($handle);

        return $rows;
    }

    private function admin(): void
    {
        $this->actingAs(User::factory()->unverified()->create(['is_admin' => true]));
    }
}
