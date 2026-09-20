<?php

namespace Tests\Feature;

use Tests\TestCase;

class DocumentationWorkflowContractTest extends TestCase
{
    public function test_primary_documentation_matches_pipeline_queues_and_retention_contract(): void
    {
        $readme = file_get_contents(base_path('README.md'));
        $runbook = file_get_contents(base_path('RUNBOOK.md'));
        $dockerSetup = file_get_contents(base_path('DOCKER-SETUP.md'));
        $docs = $readme."\n".$runbook."\n".$dockerSetup;

        foreach ([
            'ProcessSubmission',
            'ExtractSubmissionText',
            'TranslateSubmissionText',
            'ClassifySubmission',
            'GenerateSubmissionExplanation',
        ] as $stage) {
            $this->assertStringContainsString($stage, $readme);
        }

        $this->assertStringContainsString(
            'extract-text,extract-media,inference,explanation,default',
            $readme,
        );
        $this->assertStringContainsString('MEDIA_RETENTION_HOURS', $docs);
        $this->assertStringContainsString('media:prune --hours=24', $docs);
        $this->assertStringNotContainsString('media:prune --days', $docs);
        $this->assertStringNotContainsString('daily 03:00', $docs);
    }

    public function test_documented_all_in_one_development_processes_match_composer_script(): void
    {
        $composer = json_decode(file_get_contents(base_path('composer.json')), true, flags: JSON_THROW_ON_ERROR);
        $devScript = implode("\n", $composer['scripts']['dev']);
        $readme = file_get_contents(base_path('README.md'));

        foreach (['php artisan serve', 'queue:listen', 'php artisan pail', 'npm run dev', 'uvicorn'] as $process) {
            $this->assertStringContainsString($process, $devScript);
        }

        $this->assertStringContainsString('lima proses', $readme);
    }

    public function test_public_copy_does_not_claim_completion_notifications(): void
    {
        $register = file_get_contents(resource_path('views/auth/register.blade.php'));

        $this->assertStringNotContainsString('Notifikasi Hasil', $register);
        $this->assertStringContainsString('Input URL & Media', $register);
    }

    public function test_bootstrap_scripts_start_the_scheduler_service(): void
    {
        $powershell = file_get_contents(base_path('scripts/docker-setup.ps1'));
        $shell = file_get_contents(base_path('scripts/docker-setup.sh'));

        $this->assertStringContainsString("@('up', '-d', 'queue', 'scheduler')", $powershell);
        $this->assertStringContainsString('up -d queue scheduler', $shell);
        $this->assertStringContainsString('wait_service "$service" healthy,running', $shell);
    }

    public function test_primary_document_references_exist(): void
    {
        foreach (['PRD.md', 'DOCKER-SETUP.md', 'RUNBOOK.md', 'bert-service/README.md', 'TASK.md'] as $path) {
            $this->assertFileExists(base_path($path));
        }
    }
}
