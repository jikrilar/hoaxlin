<?php

namespace App\Support;

final class CsvCellSanitizer
{
    /**
     * Neutralize values that spreadsheet applications may interpret as formulas.
     */
    public function sanitize(string $value): string
    {
        if ($value === '') {
            return $value;
        }

        $hasDangerousPrefix = preg_match('/\A[\p{Z}\p{C}\s]*[=+\-@]/u', $value) === 1;

        return $hasDangerousPrefix ? "'{$value}" : $value;
    }
}
