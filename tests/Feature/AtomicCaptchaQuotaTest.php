<?php

namespace Tests\Feature;

use App\Actions\Submissions\CreateSubmission;
use App\Exceptions\AiServiceException;
use App\Models\User;
use App\Services\OpenAI\OpenAiQuota;
use App\Services\Security\CaptchaChallenge;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class AtomicCaptchaQuotaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Queue::fake();
        config([
            'security.submission.ip_daily_limit' => 30,
            'security.submission.account_daily_limit' => 100,
            'services.openai.key' => 'test-openai-key',
            'services.openai.rate_limit_per_minute' => 100,
            'services.openai.monthly_quota_usd' => 100,
            'services.openai.reservation_usd.translation' => 1,
        ]);
    }

    public function test_direct_post_without_an_issued_challenge_is_rejected(): void
    {
        $this->post(route('deteksi'), $this->textPayload(1234))
            ->assertRedirect(route('home'))
            ->assertSessionHasErrors('captcha_answer');

        $this->assertDatabaseCount('submissions', 0);
    }

    public function test_missing_and_wrong_answers_are_rejected_without_consuming_challenge(): void
    {
        $answer = $this->issueCaptcha();

        $this->post(route('deteksi'), $this->textPayload($answer + 1))
            ->assertRedirect(route('home'))
            ->assertSessionHasErrors('captcha_answer');

        $this->post(route('deteksi'), $this->textPayload($answer))
            ->assertRedirect();

        $this->assertDatabaseCount('submissions', 1);
    }

    public function test_successful_submission_consumes_challenge_and_replay_is_rejected(): void
    {
        $answer = $this->issueCaptcha();
        $issuedSession = $this->app['session.store']->get(CaptchaChallenge::SESSION_KEY);

        $this->post(route('deteksi'), $this->textPayload($answer))
            ->assertRedirect();

        $this->withSession([CaptchaChallenge::SESSION_KEY => $issuedSession])
            ->post(route('deteksi'), $this->textPayload($answer))
            ->assertRedirect(route('home'))
            ->assertSessionHasErrors('captcha_answer');

        $this->assertDatabaseCount('submissions', 1);
    }

    public function test_two_reservations_for_one_challenge_cannot_both_succeed(): void
    {
        $this->get(route('home'));
        $request = Request::create(route('deteksi'), 'POST');
        $request->setLaravelSession($this->app['session.store']);
        $captcha = app(CaptchaChallenge::class);

        $first = $captcha->reserve($request);
        $second = $captcha->reserve($request);

        $this->assertNotNull($first);
        $this->assertNull($second);
        $captcha->release($first);
        $this->assertNotNull($captcha->reserve($request));
    }

    public function test_internal_submission_failure_releases_challenge_for_safe_reuse(): void
    {
        $answer = $this->issueCaptcha();
        $action = Mockery::mock(CreateSubmission::class);
        $action->shouldReceive('handle')->once()->andThrow(new RuntimeException('internal test failure'));
        $this->app->instance(CreateSubmission::class, $action);

        try {
            $this->withoutExceptionHandling()->post(route('deteksi'), $this->textPayload($answer));
            $this->fail('Expected the internal submission exception.');
        } catch (RuntimeException $exception) {
            $this->assertSame('internal test failure', $exception->getMessage());
        }

        $state = Cache::get('captcha:challenge:'.$this->app['session.store']->get(CaptchaChallenge::SESSION_KEY.'.id'));
        $this->assertSame('issued', $state['state'] ?? null);
    }

    public function test_ip_daily_quota_is_enforced_at_the_boundary(): void
    {
        config(['security.submission.ip_daily_limit' => 2]);

        $this->submitText();
        $this->submitText();
        $this->submitText()->assertRedirect(route('home'))->assertSessionHasErrors('input_type');

        $this->assertDatabaseCount('submissions', 2);
    }

    public function test_account_daily_quota_is_enforced_at_the_boundary(): void
    {
        config([
            'security.submission.ip_daily_limit' => 10,
            'security.submission.account_daily_limit' => 2,
        ]);
        $user = User::factory()->create();

        $this->submitText($user);
        $this->submitText($user);
        $this->submitText($user)->assertRedirect(route('home'))->assertSessionHasErrors('input_type');

        $this->assertDatabaseCount('submissions', 2);
    }

    public function test_daily_quota_key_rolls_over_at_application_day_boundary(): void
    {
        config(['security.submission.ip_daily_limit' => 1]);
        $now = Carbon::create(2026, 9, 19, 23, 59, 50, config('app.timezone'));
        Carbon::setTestNow($now);

        try {
            $this->submitText();
            Carbon::setTestNow($now->copy()->addDay()->startOfDay()->addSecond());
            $this->submitText()->assertRedirect();
        } finally {
            Carbon::setTestNow();
        }

        $this->assertDatabaseCount('submissions', 2);
    }

    public function test_openai_per_minute_reservation_is_atomic_and_has_minute_boundary(): void
    {
        config(['services.openai.rate_limit_per_minute' => 2]);
        $quota = app(OpenAiQuota::class);

        $first = $quota->reserve('translation');
        $second = $quota->reserve('translation');

        try {
            $quota->reserve('translation');
            $this->fail('Expected the per-minute quota to reject the third reservation.');
        } catch (AiServiceException $exception) {
            $this->assertTrue($exception->retryable);
            $this->assertSame(429, $exception->statusCode);
        }

        $this->assertSame(2, Cache::get('openai:rate:'.now()->format('YmdHi')));
        $quota->release($first);
        $quota->release($second);
    }

    public function test_monthly_budget_survives_cache_clear_and_reconciles_usage(): void
    {
        config([
            'services.openai.monthly_quota_usd' => 1.0,
            'services.openai.reservation_usd.translation' => 0.6,
        ]);
        $quota = app(OpenAiQuota::class);
        $first = $quota->reserve('translation');
        Cache::flush();

        try {
            $quota->reserve('translation');
            $this->fail('Expected the durable monthly budget to remain exhausted by the reservation.');
        } catch (AiServiceException $exception) {
            $this->assertFalse($exception->retryable);
        }

        $quota->finalize($first, 10, 5, 0.2);
        $second = $quota->reserve('translation');
        $this->assertDatabaseHas('openai_usage_reservations', [
            'id' => $second->id,
            'status' => 'reserved',
        ]);
        $quota->release($second);
    }

    public function test_releasing_a_failed_provider_reservation_returns_monthly_budget(): void
    {
        config([
            'services.openai.monthly_quota_usd' => 1.0,
            'services.openai.reservation_usd.translation' => 0.8,
        ]);
        $quota = app(OpenAiQuota::class);
        $first = $quota->reserve('translation');
        $quota->release($first);

        $second = $quota->reserve('translation');
        $this->assertNotSame($first->id, $second->id);
        $quota->release($second);
    }

    private function issueCaptcha(): int
    {
        $this->get(route('home'))->assertOk();

        return (int) $this->app['session.store']->get(CaptchaChallenge::SESSION_KEY.'.answer');
    }

    private function submitText(?User $user = null): TestResponse
    {
        $answer = $this->issueCaptcha();
        $request = $this->withSession([CaptchaChallenge::SESSION_KEY => $this->app['session.store']->get(CaptchaChallenge::SESSION_KEY)]);
        if ($user !== null) {
            $request = $request->actingAs($user);
        }

        return $request->post(route('deteksi'), $this->textPayload($answer));
    }

    /** @return array{input_type: string, raw_input: string, captcha_answer: int} */
    private function textPayload(int $answer): array
    {
        return [
            'input_type' => 'text',
            'raw_input' => str_repeat('Berita untuk pengujian limiter atomik. ', 3),
            'captcha_answer' => $answer,
        ];
    }
}
