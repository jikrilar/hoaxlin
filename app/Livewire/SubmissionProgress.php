<?php

namespace App\Livewire;

use App\Enums\ProcessingStage;
use App\Models\Submission;
use Livewire\Component;

class SubmissionProgress extends Component
{
    public Submission $submission;

    public int $progress = 0;

    public string $stageLabel = '';

    public string $status = '';

    public bool $isCompleted = false;

    public bool $isFailed = false;

    public function mount(Submission $submission): void
    {
        $this->submission = $submission;
        $this->refreshProgress();
    }

    public function refreshProgress(): void
    {
        $this->submission->refresh();

        $this->status = $this->submission->status;
        $this->isCompleted = $this->submission->status === 'completed';
        $this->isFailed = $this->submission->status === 'failed';

        if ($this->isCompleted) {
            $this->progress = 100;
            $this->stageLabel = 'Selesai';
            return;
        }

        if ($this->isFailed) {
            $stage = ProcessingStage::tryFrom($this->submission->processing_stage ?? '');
            $this->progress = $stage?->progressPercentage() ?? 0;
            $this->stageLabel = $stage?->label() ?? 'Gagal';
            return;
        }

        $stage = ProcessingStage::tryFrom($this->submission->processing_stage ?? '');
        if ($stage) {
            $this->progress = $stage->progressPercentage();
            $this->stageLabel = $stage->label();
        } else {
            // Fallback based on status
            $this->progress = match ($this->status) {
                'pending' => 5,
                'processing' => 30,
                default => 0,
            };
            $this->stageLabel = ucfirst(str_replace('_', ' ', $this->submission->processing_stage ?? $this->status));
        }
    }

    public function render()
    {
        return view('livewire.submission-progress');
    }
}
