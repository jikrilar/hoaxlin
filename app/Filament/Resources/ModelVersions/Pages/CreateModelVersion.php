<?php

namespace App\Filament\Resources\ModelVersions\Pages;

use App\Filament\Resources\ModelVersions\ModelVersionResource;
use App\Jobs\StartBertTrainingJob;
use App\Models\ModelVersion;
use App\Services\Bert\BertCsvValidator;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

class CreateModelVersion extends CreateRecord
{
    protected static string $resource = ModelVersionResource::class;

    protected static bool $canCreateAnother = false;

    protected ?string $subheading = ModelVersionResource::TRAINING_NOTICE;

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Pelatihan versi model dimasukkan ke antrean.';
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $relativePath = trim((string) ($data['dataset_path'] ?? ''));
        $disk = Storage::disk('datasets');

        if ($relativePath === '' || ! $disk->exists($relativePath)) {
            throw ValidationException::withMessages([
                'data.dataset_path' => 'Berkas CSV pelatihan tidak ditemukan pada penyimpanan dataset.',
            ]);
        }

        $stream = $disk->readStream($relativePath);
        if (! is_resource($stream)) {
            throw ValidationException::withMessages([
                'data.dataset_path' => 'Berkas CSV pelatihan tidak dapat dibuka.',
            ]);
        }

        try {
            $stats = app(BertCsvValidator::class)->validateStream(
                $stream,
                $disk->size($relativePath),
            );
        } catch (InvalidArgumentException $exception) {
            if (is_resource($stream)) {
                fclose($stream);
            }
            $disk->delete($relativePath);

            throw ValidationException::withMessages([
                'data.dataset_path' => $exception->getMessage(),
            ]);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        $data['version_name'] = trim((string) ($data['version_name'] ?? ''));
        $data['dataset_path'] = $relativePath;
        $data['total_rows'] = $stats['total_rows'];
        $data['status'] = ModelVersion::STATUS_PENDING;
        $data['is_active'] = false;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            return parent::handleRecordCreation($data);
        } catch (Throwable $exception) {
            $path = $data['dataset_path'] ?? null;
            if (is_string($path) && $path !== '') {
                Storage::disk('datasets')->delete($path);
            }

            throw $exception;
        }
    }

    protected function afterCreate(): void
    {
        StartBertTrainingJob::dispatch((int) $this->getRecord()->getKey())
            ->onQueue('default')
            ->afterCommit();
    }
}
