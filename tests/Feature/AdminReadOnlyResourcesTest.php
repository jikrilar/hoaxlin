<?php

namespace Tests\Feature;

use App\Filament\Resources\DetectionResults\DetectionResultResource;
use App\Filament\Resources\DetectionResults\Pages\ListDetectionResults;
use App\Filament\Resources\Feedback\FeedbackResource;
use App\Filament\Resources\Feedback\Pages\ListFeedback;
use App\Filament\Resources\Submissions\Pages\ListSubmissions;
use App\Filament\Resources\Submissions\SubmissionResource;
use App\Filament\Support\ReadOnlyPresentation;
use App\Models\DetectionResult;
use App\Models\Feedback;
use App\Models\Submission;
use App\Models\User;
use App\View\Presenters\DetectionPresentation;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminReadOnlyResourcesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Http::preventStrayRequests();
    }

    #[DataProvider('resources')]
    public function test_native_pages_require_panel_admin_and_keep_all_mutations_disabled(string $resource, string $model, string $list): void
    {
        $record = $model::factory()->create();
        foreach (['index', 'view'] as $page) {
            $url = $resource::getUrl($page, ['record' => $record]);
            $this->get($url)->assertRedirect(Filament::getPanel('admin')->getLoginUrl());
            $this->actingAs(User::factory()->create(['is_admin' => false]))->get($url)->assertForbidden();
            $this->admin();
            $response = $this->get($url)->assertOk()->assertDontSee('data-admin-form-runtime-required');
            if ($page === 'index') {
                $response->assertSee('data-admin-readonly-runtime-required');
            } else {
                $response->assertDontSee('data-admin-readonly-runtime-required');
            }
            auth()->logout();
        }
        $this->admin();
        $this->assertSame(['index', 'view'], array_keys($resource::getPages()));
        $this->assertFalse($resource::canCreate());
        $this->assertFalse($resource::canEdit($record));
        $this->assertFalse($resource::canDelete($record));
        $this->assertFalse($resource::canDeleteAny());
        $index = $resource::getUrl('index');
        $this->get($index.'/create')->assertNotFound();
        $this->get($resource::getUrl('view', ['record' => $record]).'/edit')->assertNotFound();
        Livewire::test($list)->assertTableActionExists('view')->assertTableActionDoesNotExist('edit')
            ->assertTableActionDoesNotExist('delete')->assertTableBulkActionDoesNotExist('delete')->assertActionDoesNotExist('create');
        $this->assertModelExists($record);
        Http::assertNothingSent();
    }

    public static function resources(): array
    {
        return [
            'submissions' => [SubmissionResource::class, Submission::class, ListSubmissions::class],
            'results' => [DetectionResultResource::class, DetectionResult::class, ListDetectionResults::class],
            'feedback' => [FeedbackResource::class, Feedback::class, ListFeedback::class],
        ];
    }

    #[DataProvider('pipelineStates')]
    public function test_partial_classifier_records_are_distinguished_from_final_submission_results(string $status, string $label): void
    {
        $this->admin();
        $submission = Submission::factory()->create(['status' => $status, 'processing_stage' => 'explaining']);
        $result = DetectionResult::factory()->for($submission)->create(['label' => $label, 'explanation_status' => 'unavailable']);
        $expected = $status === 'completed' ? DetectionPresentation::label($label)['text'] : 'Record classifier parsial; bukan hasil final';
        Livewire::test(ListSubmissions::class)->assertTableColumnStateSet('detectionResult.label', $expected, $submission);
        $response = $this->get(SubmissionResource::getUrl('view', ['record' => $submission]))->assertOk()->assertSee($expected)->assertSee('Penyusunan penjelasan');
        if ($status !== 'completed') {
            $response->assertDontSee(DetectionPresentation::label($label)['text']);
        }
        $this->get(DetectionResultResource::getUrl('view', ['record' => $result]))->assertOk()
            ->assertSee($status === 'completed' ? 'Record classifier pada submission selesai' : 'Record classifier parsial; bukan hasil final')
            ->assertSee(DetectionPresentation::label($label)['text'])->assertSee('bukan persentase kebenaran')
            ->assertSee('bukan bukti informasi benar atau palsu')->assertSee('Penjelasan tidak tersedia.');
    }

    public static function pipelineStates(): array
    {
        return [
            'pending partial' => ['pending', 'valid'], 'processing partial' => ['processing', 'hoax'],
            'failed partial' => ['failed', 'meragukan'], 'completed valid' => ['completed', 'valid'],
            'completed hoax' => ['completed', 'hoax'], 'completed abstention' => ['completed', 'meragukan'],
        ];
    }

    public function test_submission_detail_keeps_full_escaped_input_provenance_and_private_media_as_text(): void
    {
        $this->admin();
        $long = '<script id="input-attack">literal</script>'.str_repeat("\nParagraf panjang untuk inspeksi.", 200).' FullTailFixture';
        $record = Submission::factory()->create([
            'user_id' => null, 'input_type' => 'video_url', 'raw_input' => $long, 'extracted_text' => $long,
            'translated_text' => $long, 'source_language' => 'en', 'translation_provider' => 'provider-fixture',
            'translation_model' => 'translator-fixture', 'source_url' => 'javascript:alert(1)',
            'media_path' => 'private/fixture.mp4', 'guest_access_token_hash' => 'PRIVATE_CAPABILITY_FIXTURE',
            'failure_reason' => '<script id="failure-attack">literal</script>',
        ]);
        $response = $this->get(SubmissionResource::getUrl('view', ['record' => $record]))->assertOk()
            ->assertSee($long)->assertSee('provider-fixture')->assertSee('translator-fixture')->assertSee('Tamu')
            ->assertSee('Tautan video')->assertSee('Hasil belum tersedia')->assertSee('private/fixture.mp4')
            ->assertDontSee('PRIVATE_CAPABILITY_FIXTURE')->assertDontSee('<script id="input-attack">', false)
            ->assertDontSee('<script id="failure-attack">', false)->assertDontSee('href="javascript:', false)
            ->assertDontSee('/storage/private/fixture.mp4');
        Livewire::test(ListSubmissions::class)->searchTable('FullTailFixture')->assertCanSeeTableRecords([$record])->assertDontSee($long);
        $record->update(['raw_input' => null, 'extracted_text' => null, 'translated_text' => null, 'source_language' => null, 'translation_provider' => null, 'translation_model' => null]);
        $this->get(SubmissionResource::getUrl('view', ['record' => $record]))->assertOk()->assertSee('Input teks tidak tersedia')->assertSee('Teks terjemahan tidak tersedia')->assertSee('Belum tercatat');
    }

    #[DataProvider('scores')]
    public function test_classifier_score_preserves_zero_precision_and_unavailable_without_accessor_coercion(mixed $score, ?string $expected): void
    {
        $this->assertSame($expected, ReadOnlyPresentation::confidence($score));
        $record = new DetectionResult(['confidence_score' => $score]);
        $this->assertSame($expected, ReadOnlyPresentation::confidence($record->confidence_score));
    }

    public static function scores(): array
    {
        return ['zero' => [0, '0%'], 'precision' => ['0.9234', '92,34%'], 'one' => [1, '100%'], 'missing' => [null, null], 'out of range' => [1.1, null]];
    }

    public function test_result_detail_keeps_ready_pending_unavailable_and_full_escaped_explanation(): void
    {
        $this->admin();
        $text = '<script id="explanation-attack">literal</script>'.str_repeat("\nPenjelasan panjang.", 200).' ExplanationTailFixture';
        $record = DetectionResult::factory()->create(['explanation' => $text, 'explanation_status' => 'ready', 'confidence_score' => 0, 'model_version' => '']);
        $url = DetectionResultResource::getUrl('view', ['record' => $record]);
        $this->get($url)->assertOk()->assertSee($text)->assertSee('0%')->assertSee('Versi model tidak tersedia')
            ->assertDontSee('<script id="explanation-attack">', false)
            ->assertSee(SubmissionResource::getUrl('view', ['record' => $record->submission]));
        foreach (['pending' => 'Penjelasan sedang disusun.', 'unavailable' => 'Penjelasan tidak tersedia.'] as $status => $message) {
            $record->update(['explanation_status' => $status]);
            $this->get($url)->assertOk()->assertSee($message)->assertDontSee('ExplanationTailFixture');
        }
        $record->update(['explanation_status' => 'ready', 'explanation' => null]);
        $this->get($url)->assertOk()->assertSee('Penjelasan tidak tersedia.');
    }

    public function test_feedback_boolean_is_user_assessment_with_full_comment_and_correct_submission_link(): void
    {
        $this->admin();
        $text = '<script id="comment-attack">literal</script>'.str_repeat("\nKomentar pengguna.", 200).' CommentTailFixture';
        $yes = Feedback::factory()->create(['is_correct' => true, 'comment' => $text]);
        $no = Feedback::factory()->create(['is_correct' => false, 'comment' => null]);
        foreach ([$yes, $no] as $record) {
            $this->get(FeedbackResource::getUrl('view', ['record' => $record]))->assertOk()
                ->assertSee(ReadOnlyPresentation::assessment($record->is_correct))->assertSee(ReadOnlyPresentation::FEEDBACK_NOTICE)
                ->assertSee(SubmissionResource::getUrl('view', ['record' => $record->submission]))
                ->assertDontSee('<script id="comment-attack">', false);
        }
        $this->get(FeedbackResource::getUrl('view', ['record' => $yes]))->assertSee($text);
        $this->get(FeedbackResource::getUrl('view', ['record' => $no]))->assertSee('Tidak ada komentar');
        Livewire::test(ListFeedback::class)->assertTableColumnStateSet('is_correct', true, $yes)->assertTableColumnFormattedStateSet('is_correct', 'Dinilai sesuai', $yes)
            ->assertTableColumnFormattedStateSet('is_correct', 'Dinilai tidak sesuai', $no)->assertDontSee($text)
            ->filterTable('is_correct', true)->assertCanSeeTableRecords([$yes])->assertCanNotSeeTableRecords([$no])
            ->filterTable('is_correct', false)->assertCanSeeTableRecords([$no])->assertCanNotSeeTableRecords([$yes]);
        Livewire::test(ListFeedback::class)->searchTable('CommentTailFixture')->assertCanSeeTableRecords([$yes])->assertCanNotSeeTableRecords([$no]);
    }

    public function test_native_search_sort_filters_and_pagination_keep_existing_populations(): void
    {
        $this->admin();
        $text = Submission::factory()->create(['input_type' => 'text', 'status' => 'pending', 'raw_input' => 'SearchFixture']);
        $videoUrl = Submission::factory()->create(['input_type' => 'video_url', 'status' => 'processing', 'raw_input' => null, 'source_url' => 'https://example.test/UniqueSourceFixture']);
        $done = Submission::factory()->create(['input_type' => 'url', 'status' => 'completed']);
        Livewire::test(ListSubmissions::class)->searchTable('UniqueSourceFixture')->assertCanSeeTableRecords([$videoUrl])->assertCanNotSeeTableRecords([$text, $done]);
        $page = Livewire::test(ListSubmissions::class)->filterTable('input_type', 'text')->assertCanSeeTableRecords([$text])->assertCanNotSeeTableRecords([$videoUrl, $done]);
        $this->assertSame(['text', 'image', 'video', 'url'], array_keys($page->instance()->getTable()->getFilter('input_type')->getOptions()));
        Livewire::test(ListSubmissions::class)->filterTable('status', 'completed')->assertCanSeeTableRecords([$done])->assertCanNotSeeTableRecords([$text, $videoUrl]);
        $low = DetectionResult::factory()->for($text)->create(['label' => 'valid', 'confidence_score' => 0.1250, 'model_version' => 'LowModelFixture']);
        $high = DetectionResult::factory()->for($done)->create(['label' => 'hoax', 'confidence_score' => 0.9234]);
        Livewire::test(ListDetectionResults::class)->sortTable('confidence_percentage', 'desc')->assertCanSeeTableRecords([$high, $low], inOrder: true)
            ->assertTableColumnStateSet('confidence_percentage', '92,34%', $high);
        Livewire::test(ListDetectionResults::class)->filterTable('label', 'valid')->assertCanSeeTableRecords([$low])->assertCanNotSeeTableRecords([$high]);
        Livewire::test(ListDetectionResults::class)->searchTable('LowModelFixture')->assertCanSeeTableRecords([$low])->assertCanNotSeeTableRecords([$high]);
        $records = Submission::factory()->count(7)->create();
        Livewire::test(ListSubmissions::class)->sortTable('id', 'asc')->set('tableRecordsPerPage', 5)->assertCanSeeTableRecords([$text, $videoUrl, $done, $records[0], $records[1]])
            ->assertCanNotSeeTableRecords([$records[2]])->call('gotoPage', 2)->assertCanSeeTableRecords($records->slice(2))->assertCanNotSeeTableRecords([$text]);
    }

    private function admin(): void
    {
        $this->actingAs(User::factory()->unverified()->create(['is_admin' => true]));
    }
}
