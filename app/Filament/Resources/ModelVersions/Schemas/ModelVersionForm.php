<?php

namespace App\Filament\Resources\ModelVersions\Schemas;

use App\Models\ModelVersion;
use App\Services\Bert\BertCsvValidator;
use Closure;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use InvalidArgumentException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ModelVersionForm
{
    public static function configure(Schema $schema): Schema
    {
        $maxBytes = max(1024, (int) config('services.bert.max_train_csv_bytes', 26214400));
        $maxKilobytes = (int) floor($maxBytes / 1024);
        $maxMbLabel = $maxBytes % 1048576 === 0
            ? (string) ($maxBytes / 1048576).' MB'
            : number_format($maxBytes / 1048576, 1, '.', '').' MB';

        return $schema
            ->components([
                Section::make('Identitas versi model')
                    ->description('Nama versi menjadi identitas artefak baru di penyimpanan model BERT dan tidak dapat menimpa baseline v1.0.0.')
                    ->schema([
                        TextInput::make('version_name')
                            ->label('Nama versi model')
                            ->placeholder('Contoh: v1.1.0 atau v1.1-202611')
                            ->trim()
                            ->required()
                            ->maxLength(64)
                            ->unique(ModelVersion::class, 'version_name')
                            ->regex('/\A[A-Za-z0-9][A-Za-z0-9._-]{0,63}\z/')
                            ->rule(static function (): Closure {
                                return static function (string $attribute, mixed $value, Closure $fail): void {
                                    $cleaned = trim((string) $value);
                                    if ($cleaned === '') {
                                        return;
                                    }

                                    if (str_contains($cleaned, '..')) {
                                        $fail('Nama versi model tidak boleh mengandung "..".');

                                        return;
                                    }

                                    $lower = strtolower($cleaned);
                                    if ($lower === 'indobert-hoax') {
                                        $fail('Nama versi model "indobert-hoax" dicadangkan oleh sistem.');

                                        return;
                                    }

                                    if ($lower === 'v1.0.0') {
                                        $fail('Nama versi model "v1.0.0" tidak boleh menimpa baseline terverifikasi.');
                                    }
                                };
                            })
                            ->helperText('Gunakan huruf, angka, titik, garis bawah, atau tanda hubung (maks. 64 karakter). Tidak boleh memuat ".." atau memakai "v1.0.0".')
                            ->validationMessages([
                                'required' => 'Masukkan nama versi model.',
                                'max' => 'Nama versi model maksimal 64 karakter.',
                                'unique' => 'Nama versi model sudah digunakan. Pilih nama versi lain.',
                                'regex' => 'Nama versi model harus diawali huruf/angka dan hanya memuat huruf, angka, titik, garis bawah, atau tanda hubung.',
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
                Section::make('Dataset pelatihan CSV')
                    ->description('Unggah dataset berlabel untuk incremental fine-tuning. Label yang didukung: valid dan hoax (alias seperti fakta/benar/hoaks/1 juga dikenali; meragukan ditolak).')
                    ->schema([
                        FileUpload::make('dataset_path')
                            ->label('Berkas CSV pelatihan')
                            ->disk('datasets')
                            ->directory('batches')
                            ->visibility('public')
                            ->required()
                            ->acceptedFileTypes([
                                'text/csv',
                                'text/plain',
                                'application/csv',
                                'application/vnd.ms-excel',
                            ])
                            ->rules(['bail', 'extensions:csv'])
                            ->maxSize($maxKilobytes)
                            ->rule(static function (): Closure {
                                return static function (string $attribute, mixed $value, Closure $fail): void {
                                    if (! $value instanceof TemporaryUploadedFile) {
                                        return;
                                    }

                                    if (strtolower((string) $value->getClientOriginalExtension()) !== 'csv') {
                                        return;
                                    }

                                    $validator = app(BertCsvValidator::class);

                                    try {
                                        $realPath = $value->getRealPath();
                                        if (is_string($realPath) && $realPath !== '' && is_file($realPath)) {
                                            $validator->validatePath($realPath);

                                            return;
                                        }

                                        $stream = $value->readStream();
                                        if (! is_resource($stream)) {
                                            $fail('Berkas CSV tidak dapat dibaca.');

                                            return;
                                        }

                                        try {
                                            $size = $value->getSize();
                                            $validator->validateStream($stream, is_int($size) ? $size : null);
                                        } finally {
                                            if (is_resource($stream)) {
                                                fclose($stream);
                                            }
                                        }
                                    } catch (InvalidArgumentException $exception) {
                                        $fail($exception->getMessage());
                                    }
                                };
                            })
                            ->helperText("Berkas CSV UTF-8 wajib memiliki kolom header `text` dan `label`, minimal 1 baris data valid/hoax, dan maksimal {$maxMbLabel}.")
                            ->validationMessages([
                                'required' => 'Unggah berkas CSV pelatihan.',
                                'mimetypes' => 'Berkas harus bertipe CSV yang valid.',
                                'extensions' => 'Berkas harus berekstensi .csv.',
                                'max' => 'Ukuran berkas CSV melebihi batas maksimal yang diizinkan.',
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
