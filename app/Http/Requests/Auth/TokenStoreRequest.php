<?php

namespace App\Http\Requests\Auth;

use App\Http\Middleware\EnsureTokenAbility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TokenStoreRequest extends FormRequest
{
    /**
     * A token may only mint tokens with a subset of its own abilities;
     * otherwise a leaked read-only token could issue itself a write token.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        return collect((array) $this->input('abilities', []))
            ->filter(fn (mixed $ability) => is_string($ability))
            ->every(fn (string $ability) => $user->tokenCan($ability));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => ['distinct', Rule::in(EnsureTokenAbility::ABILITIES)],
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:'.self::maxLifetimeDays()],
        ];
    }

    /**
     * Sanctum's global expiration (config/sanctum.php) wins over a token's
     * own expires_at, so a longer lifetime would be a promise the API cannot
     * keep (FIND-015).
     */
    public static function maxLifetimeDays(): int
    {
        $minutes = config('sanctum.expiration');

        return is_numeric($minutes) ? max(1, intdiv((int) $minutes, 24 * 60)) : 365;
    }
}
