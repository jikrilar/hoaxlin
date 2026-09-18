<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class SchedulerConfigurationTest extends TestCase
{
    public function test_stale_recovery_and_media_pruning_are_scheduled_without_overlap(): void
    {
        $events = collect(app(Schedule::class)->events());
        $staleRecovery = $events->first(
            fn ($event): bool => str_contains($event->command ?? '', 'submissions:recover-stale'),
        );
        $mediaPruning = $events->first(
            fn ($event): bool => str_contains($event->command ?? '', 'media:prune'),
        );

        $this->assertNotNull($staleRecovery);
        $this->assertSame('*/5 * * * *', $staleRecovery->expression);
        $this->assertTrue($staleRecovery->withoutOverlapping);
        $this->assertNotNull($mediaPruning);
        $this->assertSame('* * * * *', $mediaPruning->expression);
        $this->assertTrue($mediaPruning->withoutOverlapping);
    }

    public function test_compose_has_one_scheduler_with_shared_environment_and_no_host_port(): void
    {
        $compose = file_get_contents(base_path('docker-compose.yml'));
        $matched = preg_match(
            '/^  scheduler:\R(?<service>.*?)(?=^  mysql:\R)/ms',
            $compose,
            $matches,
        );

        $this->assertSame(1, $matched);
        $service = $matches['service'];
        $this->assertStringContainsString('image: hoaxlin-app:local', $service);
        $this->assertStringContainsString('restart: unless-stopped', $service);
        $this->assertStringContainsString('environment: *laravel-environment', $service);
        $this->assertStringContainsString('laravel_storage:/var/www/html/storage', $service);
        $this->assertStringContainsString('mysql:', $service);
        $this->assertStringContainsString('redis:', $service);
        $this->assertStringContainsString('command: ["php", "artisan", "schedule:work"]', $service);
        $this->assertStringNotContainsString('ports:', $service);
        $this->assertSame(1, substr_count($compose, 'schedule:work'));
    }
}
