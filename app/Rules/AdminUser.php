<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class AdminUser implements ValidationRule
{
    /**
     * @param  Closure(string, ?string=): void  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $isAdmin = User::query()
            ->admins()
            ->whereKey($value)
            ->exists();

        if (! $isAdmin) {
            $fail('Verifier dataset harus merupakan administrator.');
        }
    }
}
