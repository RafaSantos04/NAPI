<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * URL-safe identifier of organizations and of the project tree: lowercase
 * letters and digits in groups joined by single hyphens. Uniqueness stays
 * with each request, since the parent that scopes it differs by level.
 */
class Slug implements ValidationRule
{
    public const PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Same message as the `regex` rule it replaces: no contract change.
        if (! is_string($value) || preg_match(self::PATTERN, $value) !== 1) {
            $fail('validation.regex')->translate();
        }
    }
}
