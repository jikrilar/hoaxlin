<?php

namespace Tests\Feature;

use App\Filament\Resources\AdminLogs\AdminLogResource;
use App\Filament\Resources\Feedback\FeedbackResource;
use App\Filament\Resources\Feedback\Pages\ListFeedback;
use App\Filament\Resources\Feedback\Pages\ViewFeedback;
use App\Filament\Resources\Submissions\SubmissionResource;
use App\Models\AdminLog;
use App\Models\Feedback;
use App\Models\Submission;
use App\Models\User;
use App\Services\Admin\FeedbackReview;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

/** T3.7: reviewing feedback without leaving the panel. */
class AdminFeedbackReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Http::preventStrayRequests();
    }

    public function test_review_columns_are_additive_and_reversible(): void
    {
        $this->assertTrue(Schema::hasColumn('feedback', 'reviewed_at'));
        $this->assertTrue(Schema::hasColumn('feedback', 'reviewed_by'));

        $migration = require database_path('migrations/2026_11_13_000002_add_review_columns_to_feedback_table.php');
        $migration->down();

        $this->assertFalse(Schema::hasColumn('feedback', 'reviewed_at'));
        $this->assertFalse(Schema::hasColumn('feedback', 'reviewed_by'));
        $this->assertTrue(Schema::hasColumn('feedback', 'comment'), 'reverting the review columns must not touch existing data columns');

        $migration->up();

        $this->assertTrue(Schema::hasColumn('feedback', 'reviewed_at'));
        $this->assertTrue(Schema::hasColumn('feedback', 'reviewed_by'));
    }

    public function test_review_columns_are_nullable_and_survive_deleting_the_reviewer(): void
    {
        $reviewer = User::factory()->create(['is_admin' => true]);
        $feedback = Feedback::factory()->create(['reviewed_at' => now(), 'reviewed_by' => $reviewer->id]);

        $this->assertNotNull($feedback->fresh()->reviewed_at);
        $reviewer->delete();

        $feedback->refresh();
        $this->assertNull($feedback->reviewed_by, 'deleting the reviewer must not delete the feedback');
        $this->assertNotNull($feedback->reviewed_at);
    }

    public function test_list_offers_pending_incorrect_and_all_tabs_with_counts(): void
    {
        $this->admin();
        $pendingIncorrect = Feedback::factory()->create(['is_correct' => false]);
        $pendingCorrect = Feedback::factory()->create(['is_correct' => true]);
        $reviewedIncorrect = Feedback::factory()->create(['is_correct' => false, 'reviewed_at' => now(), 'reviewed_by' => auth()->id()]);

        $page = Livewire::test(ListFeedback::class);
        $tabs = $page->instance()->getCachedTabs();

        $this->assertSame(['pending', 'incorrect', 'all'], array_keys($tabs));
        $this->assertSame(['Perlu ditinjau', 'Tidak sesuai', 'Semua'], array_map(fn ($tab): string => (string) $tab->getLabel(), array_values($tabs)));
        $this->assertSame('pending', $page->instance()->activeTab, 'review work leads the list');

        $this->assertSame(2, (int) $tabs['pending']->getBadge());
        $this->assertSame(2, (int) $tabs['incorrect']->getBadge());
        $this->assertSame(3, (int) $tabs['all']->getBadge());

        $this->assertNotNull($pendingIncorrect);
        $this->assertNotNull($pendingCorrect);
        $this->assertNotNull($reviewedIncorrect);
    }

    public function test_pending_tab_contains_only_unreviewed_feedback(): void
    {
        $this->admin();
        $pending = Feedback::factory()->create();
        $reviewed = Feedback::factory()->create(['reviewed_at' => now(), 'reviewed_by' => auth()->id()]);

        Livewire::test(ListFeedback::class)
            ->assertCanSeeTableRecords([$pending])
            ->assertCanNotSeeTableRecords([$reviewed]);
    }

    public function test_incorrect_tab_contains_every_incorrect_assessment_regardless_of_review_state(): void
    {
        $this->admin();
        $pendingIncorrect = Feedback::factory()->create(['is_correct' => false]);
        $reviewedIncorrect = Feedback::factory()->create(['is_correct' => false, 'reviewed_at' => now(), 'reviewed_by' => auth()->id()]);
        $correct = Feedback::factory()->create(['is_correct' => true]);

        Livewire::test(ListFeedback::class)->set('activeTab', 'incorrect')
            ->assertCanSeeTableRecords([$pendingIncorrect, $reviewedIncorrect])
            ->assertCanNotSeeTableRecords([$correct]);
    }

    public function test_marking_reviewed_records_the_admin_and_the_time(): void
    {
        $admin = $this->admin();
        $feedback = Feedback::factory()->create(['is_correct' => false]);

        Livewire::test(ListFeedback::class)->callTableAction('markReviewed', $feedback);

        $feedback->refresh();
        $this->assertNotNull($feedback->reviewed_at);
        $this->assertSame($admin->id, $feedback->reviewed_by);
        $this->assertSame($admin->id, Feedback::query()->find($feedback->id)->reviewed_by);
    }

    public function test_marking_reviewed_is_idempotent_and_keeps_the_first_reviewer(): void
    {
        $first = $this->admin();
        $feedback = Feedback::factory()->create();
        $this->travelTo('2026-11-13 10:00:00');
        Livewire::test(ListFeedback::class)->callTableAction('markReviewed', $feedback);

        // The reviewed row leaves the "Perlu ditinjau" tab, so the second attempt
        // goes through the service — the same claim the action calls.
        $second = $this->admin();
        $this->travelTo('2026-11-14 10:00:00');
        Livewire::test(ListFeedback::class)->assertCanNotSeeTableRecords([$feedback->fresh()]);
        $this->assertFalse(app(FeedbackReview::class)->markReviewed($feedback->fresh()));

        $feedback->refresh();
        $this->assertSame($first->id, $feedback->reviewed_by, 'the first reviewer stays recorded');
        $this->assertSame('2026-11-13 10:00:00', $feedback->reviewed_at->format('Y-m-d H:i:s'));
        $this->assertNotSame($second->id, $feedback->reviewed_by);
    }

    public function test_bulk_mark_reviewed_only_touches_unreviewed_rows(): void
    {
        $admin = $this->admin();
        $first = Feedback::factory()->create();
        $second = Feedback::factory()->create();
        $already = Feedback::factory()->create(['reviewed_at' => now()->subDay(), 'reviewed_by' => $admin->id]);
        $originalReviewedAt = $already->reviewed_at;

        Livewire::test(ListFeedback::class)
            ->callTableBulkAction('markReviewedSelected', [$first, $second, $already]);

        $this->assertSame($admin->id, $first->fresh()->reviewed_by);
        $this->assertSame($admin->id, $second->fresh()->reviewed_by);
        $this->assertSame($originalReviewedAt->toDateTimeString(), $already->fresh()->reviewed_at->toDateTimeString());
    }

    public function test_mark_reviewed_writes_an_allowlisted_admin_log_without_user_content(): void
    {
        $admin = $this->admin();
        $feedback = Feedback::factory()->create(['comment' => 'PRIVATE_REVIEW_COMMENT', 'is_correct' => false]);

        Livewire::test(ListFeedback::class)->callTableAction('markReviewed', $feedback);

        $log = AdminLog::query()->withAction('feedback_reviewed')->sole();
        $this->assertSame($admin->id, $log->admin_id);
        $this->assertSame('feedback', $log->target_table);
        $this->assertSame($feedback->id, $log->target_id);
        $this->assertSame(1, $log->metadata['count']);
        $this->assertSame([$feedback->id], $log->metadata['feedback_ids']);
        $encoded = json_encode($log->metadata, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('PRIVATE_REVIEW_COMMENT', $encoded);
        $this->assertArrayNotHasKey('comment', $log->metadata);

        $this->get(AdminLogResource::getUrl('view', ['record' => $log]))->assertOk()
            ->assertSee('Umpan balik ditinjau')
            ->assertDontSee('PRIVATE_REVIEW_COMMENT');
    }

    public function test_review_actions_require_an_admin(): void
    {
        $feedback = Feedback::factory()->create();

        $this->get(FeedbackResource::getUrl('index'))->assertRedirect('/admin/login');
        $this->actingAs(User::factory()->create(['is_admin' => false]));
        $this->get(FeedbackResource::getUrl('index'))->assertForbidden();
        $this->assertNull($feedback->fresh()->reviewed_at);
        $this->assertSame(0, AdminLog::count());
    }

    public function test_review_action_is_offered_on_the_detail_page_and_disappears_once_reviewed(): void
    {
        $admin = $this->admin();
        $feedback = Feedback::factory()->create();

        Livewire::test(ViewFeedback::class, ['record' => $feedback->getRouteKey()])
            ->assertActionVisible('markReviewed')
            ->callAction('markReviewed')
            ->assertHasNoActionErrors();

        $this->assertSame($admin->id, $feedback->fresh()->reviewed_by);
        Livewire::test(ViewFeedback::class, ['record' => $feedback->getRouteKey()])
            ->assertActionHidden('markReviewed');
    }

    public function test_detail_shows_review_state_and_reviewer(): void
    {
        $admin = $this->admin();
        $feedback = Feedback::factory()->create();
        $this->get(FeedbackResource::getUrl('view', ['record' => $feedback]))->assertOk()
            ->assertSee('Belum ditinjau');

        Livewire::test(ViewFeedback::class, ['record' => $feedback->getRouteKey()])->callAction('markReviewed');

        $this->get(FeedbackResource::getUrl('view', ['record' => $feedback->fresh()]))->assertOk()
            ->assertSee('Sudah ditinjau')
            ->assertSee($admin->name);
    }

    public function test_list_still_links_to_the_related_submission_and_exports_csv(): void
    {
        $this->admin();
        $feedback = Feedback::factory()->create(['comment' => '=cmd|calc']);

        $this->get(FeedbackResource::getUrl('view', ['record' => $feedback]))->assertOk()
            ->assertSee(SubmissionResource::getUrl('view', ['record' => $feedback->submission]));

        $csv = base64_decode(data_get(
            Livewire::test(ListFeedback::class)->callTableAction('exportCsv')->assertFileDownloaded()->effects,
            'download.content',
        ));

        $this->assertStringContainsString("'=cmd|calc", $csv, 'formula-like comments stay neutralised');
        $this->assertStringContainsString('ID pemeriksaan', $csv);
    }

    public function test_curation_action_is_not_rendered_until_stage_four(): void
    {
        $this->admin();
        $feedback = Feedback::factory()->create();

        Livewire::test(ListFeedback::class)
            ->assertTableActionDoesNotExist('addToCuration')
            ->assertTableBulkActionDoesNotExist('addToCuration');
        Livewire::test(ViewFeedback::class, ['record' => $feedback->getRouteKey()])
            ->assertActionDoesNotExist('addToCuration');
    }

    public function test_review_state_survives_a_reload_and_is_queryable(): void
    {
        $admin = $this->admin();
        $feedback = Feedback::factory()->create();
        Livewire::test(ListFeedback::class)->callTableAction('markReviewed', $feedback);

        $this->assertSame(1, Feedback::query()->reviewed()->count());
        $this->assertSame(0, Feedback::query()->pendingReview()->count());
        $this->assertSame($admin->id, Feedback::query()->reviewed()->sole()->reviewed_by);

        // A fresh mount reads the same session-backed tab, so the reviewed row is gone.
        Livewire::test(ListFeedback::class)->assertCanNotSeeTableRecords([$feedback]);
    }

    public function test_reviewed_by_foreign_key_is_nullable_at_the_database_level(): void
    {
        $feedback = Feedback::factory()->create();
        DB::table('feedback')->where('id', $feedback->id)->update(['reviewed_at' => now(), 'reviewed_by' => null]);

        $this->assertNull($feedback->fresh()->reviewed_by);
        $this->assertNotNull($feedback->fresh()->reviewed_at);
        $this->assertSame(0, Submission::query()->count() - 1);
    }

    private function admin(): User
    {
        $admin = User::factory()->unverified()->create(['is_admin' => true]);
        $this->actingAs($admin);

        return $admin;
    }
}
