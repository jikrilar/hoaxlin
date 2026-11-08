<?php

namespace App\Services\Bert;

use InvalidArgumentException;

class BertCsvValidator
{
    /**
     * @var list<string>
     */
    private const VALID_LABEL_ALIASES = [
        'valid',
        '0',
        'true',
        'benar',
        'fakta',
        'fact',
        'info',
    ];

    /**
     * @var list<string>
     */
    private const HOAX_LABEL_ALIASES = [
        'hoax',
        'hoaks',
        'hoaks:',
        'hoax:',
        '1',
        'false',
        'salah',
        'keliru',
        'misinformasi',
        'disinformasi',
        'penipuan',
    ];

    /**
     * @var list<string>
     */
    private const ABSTAIN_LABELS = [
        'meragukan',
        'uncertain',
    ];

    /**
     * Validate a training CSV against the bert-service contract before persistence/queueing.
     *
     * @return array{total_rows: int, valid_rows: int, hoax_rows: int}
     */
    public function validatePath(string $path, ?int $maxBytes = null): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new InvalidArgumentException('Berkas CSV tidak ditemukan atau tidak dapat dibaca.');
        }

        $size = filesize($path);
        if ($size === false || $size === 0) {
            throw new InvalidArgumentException('Berkas CSV kosong.');
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new InvalidArgumentException('Gagal membuka berkas CSV.');
        }

        try {
            return $this->validateStream($handle, $size, $maxBytes);
        } finally {
            fclose($handle);
        }
    }

    /**
     * Validate a readable CSV stream against the bert-service contract.
     *
     * @param  resource  $handle
     * @return array{total_rows: int, valid_rows: int, hoax_rows: int}
     */
    public function validateStream(mixed $handle, ?int $size = null, ?int $maxBytes = null): array
    {
        if (! is_resource($handle)) {
            throw new InvalidArgumentException('Stream berkas CSV tidak valid.');
        }

        $knownSize = $size;
        if ($knownSize === null) {
            $stat = fstat($handle);
            if (is_array($stat) && isset($stat['size'])) {
                $knownSize = (int) $stat['size'];
            }
        }

        if ($knownSize === 0) {
            throw new InvalidArgumentException('Berkas CSV kosong.');
        }

        $limit = $maxBytes ?? (int) config('services.bert.max_train_csv_bytes', 26214400);
        if ($limit > 0 && $knownSize !== null && $knownSize > $limit) {
            $limitMb = round($limit / 1048576, 1);
            throw new InvalidArgumentException("Ukuran berkas CSV melebihi batas maksimal {$limitMb} MB.");
        }

        $meta = stream_get_meta_data($handle);
        if ($meta['seekable'] ?? false) {
            rewind($handle);
        }

        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            if ($meta['seekable'] ?? false) {
                rewind($handle);
            }
        }

        $header = fgetcsv($handle, null, ',', '"', '');
        if ($header === false || $header === [null]) {
            throw new InvalidArgumentException('Berkas CSV harus memiliki baris header dengan kolom `text` dan `label`.');
        }

        $headerMap = [];
        foreach ($header as $index => $column) {
            $cell = (string) ($column ?? '');
            if (! mb_check_encoding($cell, 'UTF-8')) {
                throw new InvalidArgumentException('Berkas CSV harus menggunakan encoding UTF-8 yang valid.');
            }

            $key = mb_strtolower(trim($cell));
            if ($key !== '' && ! array_key_exists($key, $headerMap)) {
                $headerMap[$key] = $index;
            }
        }

        if (! array_key_exists('text', $headerMap) || ! array_key_exists('label', $headerMap)) {
            throw new InvalidArgumentException('Header CSV wajib memiliki kolom `text` dan `label`.');
        }

        $textIndex = $headerMap['text'];
        $labelIndex = $headerMap['label'];
        $totalRows = 0;
        $validRows = 0;
        $hoaxRows = 0;
        $rowNumber = 1;

        while (($row = fgetcsv($handle, null, ',', '"', '')) !== false) {
            $rowNumber++;

            if ($limit > 0) {
                $offset = ftell($handle);
                if (is_int($offset) && $offset > $limit) {
                    $limitMb = round($limit / 1048576, 1);
                    throw new InvalidArgumentException("Ukuran berkas CSV melebihi batas maksimal {$limitMb} MB.");
                }
            }

            foreach ($row as $cell) {
                if ($cell !== null && ! mb_check_encoding((string) $cell, 'UTF-8')) {
                    throw new InvalidArgumentException("Baris {$rowNumber}: encoding teks bukan UTF-8 yang valid.");
                }
            }

            $text = trim((string) ($row[$textIndex] ?? ''));
            $rawLabel = trim((string) ($row[$labelIndex] ?? ''));

            if ($text === '' && $rawLabel === '') {
                continue;
            }

            if ($text === '') {
                throw new InvalidArgumentException("Baris {$rowNumber}: kolom `text` tidak boleh kosong.");
            }

            if ($rawLabel === '') {
                throw new InvalidArgumentException("Baris {$rowNumber}: kolom `label` tidak boleh kosong.");
            }

            $normalized = mb_strtolower($rawLabel);
            if (in_array($normalized, self::ABSTAIN_LABELS, true)) {
                throw new InvalidArgumentException("Baris {$rowNumber}: label `meragukan` berasal dari threshold confidence dan tidak dapat dilatih; gunakan `valid` atau `hoax`.");
            }

            if (in_array($normalized, self::VALID_LABEL_ALIASES, true)) {
                $validRows++;
            } elseif (in_array($normalized, self::HOAX_LABEL_ALIASES, true)) {
                $hoaxRows++;
            } else {
                throw new InvalidArgumentException("Baris {$rowNumber}: label `{$rawLabel}` tidak dikenali; gunakan `valid` atau `hoax`.");
            }

            $totalRows++;
        }

        if ($totalRows < 1) {
            throw new InvalidArgumentException('Berkas CSV tidak memiliki baris data pelatihan yang valid.');
        }

        return [
            'total_rows' => $totalRows,
            'valid_rows' => $validRows,
            'hoax_rows' => $hoaxRows,
        ];
    }
}
