<?php

namespace App\Support;

final class RetryAfter
{
    public static function seconds(?string $value): ?int
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = trim($value);
        if (ctype_digit($value)) {
            $seconds = (int) $value;

            return $seconds > 0 ? $seconds : null;
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return null;
        }

        $seconds = $timestamp - time();

        return $seconds > 0 ? $seconds : null;
    }
}
