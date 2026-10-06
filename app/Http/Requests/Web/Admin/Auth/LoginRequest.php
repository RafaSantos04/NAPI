<?php

namespace App\Http\Requests\Web\Admin\Auth;

use App\Http\Admin\AdminNavigation;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Session login for the admin area. Kept apart from the API LoginRequest:
 * the web flow answers with redirects and flashed errors, never JSON, and
 * must not reveal whether the e-mail exists or the account is inactive.
 */
class LoginRequest extends FormRequest
{
    private const MAX_ATTEMPTS = 5;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Informe o e-mail.',
            'email.email' => 'Informe um e-mail válido.',
            'password.required' => 'Informe a senha.',
        ];
    }

    /**
     * Inactive and soft-deleted accounts fail exactly like a wrong password.
     *
     * Valid credentials without access to the admin area get no session and
     * a message of their own (Phase 4.2). It reveals nothing new: the same
     * credentials already succeed on POST /api/v1/auth/login.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $credentials = [
            'email' => $this->string('email')->toString(),
            'password' => $this->string('password')->toString(),
            'is_active' => true,
        ];

        $guard = Auth::guard('web');

        if (! $guard->attempt($credentials)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => 'Credenciais inválidas.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        $user = $guard->user();

        if (! $user instanceof User || ! AdminNavigation::allows($user)) {
            $guard->logout();

            throw ValidationException::withMessages([
                'email' => 'Esta conta não tem acesso à área administrativa.',
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    private function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => "Muitas tentativas. Tente novamente em {$seconds} segundos.",
        ]);
    }

    private function throttleKey(): string
    {
        return 'admin-login:'.Str::transliterate(Str::lower($this->string('email')->toString())).'|'.$this->ip();
    }
}
