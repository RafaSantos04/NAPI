<?php

namespace App\Http\Requests\Auth;

use App\Http\Middleware\EnsureTokenAbility;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Laravel\Sanctum\PersonalAccessToken;

class TokenStoreRequest extends FormRequest
{
    /**
     * A privileged account issues tokens only in the admin area (ADR-0013,
     * item 12). Otherwise, a token may only mint tokens with a subset of its
     * own abilities; a leaked read-only token could issue itself a write
     * token.
     */
    public function authorize(): bool|Response
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        $issuance = Gate::forUser($user)->inspect('issueViaApi', PersonalAccessToken::class);

        if ($issuance->denied()) {
            return $issuance;
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
