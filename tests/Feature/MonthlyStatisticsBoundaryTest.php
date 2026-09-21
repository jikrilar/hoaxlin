<?php

namespace Tests\Feature;

use App\Models\DetectionResult;
use App\Models\Submission;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class MonthlyStatisticsBoundaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.timezone' => 'Asia/Jakarta']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_monthly_metrics_use_a_half_open_range_at_the_exact_month_boundary(): void
    {
        $this->freezeAt('2026-09-15 12:00:00');

        $this->submissionAt('2026-08-01 00:00:00', 'valid');
        $this->submissionAt('2026-08-31 23:59:59', 'hoax');
        $this->submissionAt('2026-09-01 00:00:00', 'valid');
        $this->submissionAt('2026-08-20 08:00:00');

        $trend = $this->trend();
        $august = $this->month($trend, 'Aug 2026');
        $september = $this->month($trend, 'Sep 2026');

        $this->assertSame(3, $august['total']);
        $this->assertSame(1, $august['hoax']);
        $this->assertSame(1, $august['valid']);
        $this->assertSame(1, $september['total']);
        $this->assertSame(0, $september['hoax']);
        $this->assertSame(1, $september['valid']);
    }

    public function test_december_and_january_have_non_overlapping_boundaries(): void
    {
        $this->freezeAt('2027-01-15 12:00:00');

        $this->submissionAt('2026-12-31 23:59:59', 'hoax');
        $this->submissionAt('2027-01-01 00:00:00', 'valid');

        $trend = $this->trend();
        $december = $this->month($trend, 'Dec 2026');
        $january = $this->month($trend, 'Jan 2027');

        $this->assertSame(1, $december['total']);
        $this->assertSame(1, $december['hoax']);
        $this->assertSame(0, $december['valid']);
        $this->assertSame(1, $january['total']);
        $this->assertSame(0, $january['hoax']);
        $this->assertSame(1, $january['valid']);
    }

    public function test_month_boundaries_are_calculated_in_the_application_timezone(): void
    {
        $this->assertSame('Asia/Jakarta', config('app.timezone'));
        $this->freezeAt('2026-09-15 12:00:00');

        $this->submissionAt('2026-08-31 23:59:59', 'hoax');
        $this->submissionAt('2026-09-01 00:00:00', 'valid');

        $trend = $this->trend();
        $august = $this->month($trend, 'Aug 2026');
        $september = $this->month($trend, 'Sep 2026');

        // The application month boundary is 00:00 Asia/Jakarta, so the
        // second record belongs to September and not to August.
        $this->assertSame(1, $august['total']);
        $this->assertSame(1, $august['hoax']);
        $this->assertSame(0, $august['valid']);
        $this->assertSame(1, $september['total']);
        $this->assertSame(0, $september['hoax']);
        $this->assertSame(1, $september['valid']);
    }

    public function test_trend_has_deterministic_twelve_month_order(): void
    {
        $this->freezeAt('2026-09-15 12:00:00');

        $trend = $this->trend();

        $this->assertCount(12, $trend);
        $this->assertSame(
            [
                'Oct 2025', 'Nov 2025', 'Dec 2025', 'Jan 2026', 'Feb 2026', 'Mar 2026',
                'Apr 2026', 'May 2026', 'Jun 2026', 'Jul 2026', 'Aug 2026', 'Sep 2026',
            ],
            $trend->pluck('label')->all(),
        );
    }

    private function freezeAt(string $timestamp): void
    {
        Carbon::setTestNow(Carbon::createFromFormat('Y-m-d H:i:s', $timestamp, config('app.timezone')));
    }

    private function submissionAt(string $timestamp, ?string $label = null): Submission
    {
        $createdAt = Carbon::createFromFormat('Y-m-d H:i:s', $timestamp, config('app.timezone'));
        $submission = Submission::factory()->create([
            'status' => $label === null ? 'pending' : 'completed',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
            'processing_completed_at' => $label === null ? null : $createdAt,
        ]);

        if ($label !== null) {
            DetectionResult::factory()->create([
                'submission_id' => $submission->id,
                'label' => $label,
            ]);
        }

        return $submission;
    }

    /** @return Collection<int, array{label: string, total: int, hoax: int, valid: int}> */
    private function trend(): Collection
    {
        return $this->get(route('statistik'))
            ->assertOk()
            ->viewData('trend');
    }

    /** @param Collection<int, array{label: string, total: int, hoax: int, valid: int}> $trend */
    private function month(Collection $trend, string $label): array
    {
        return $trend->firstWhere('label', $label);
    }
}
