<?php

namespace App\Http\Requests\Web\Admin\Auth;

use App\Domain\IAM\Actions\VerifyLoginCredentials;
use App\Domain\IAM\Exceptions\TooManyLoginAttemptsException;
use App\Http\Admin\AdminNavigation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Session login for the admin area. Kept apart from the API LoginRequest:
 * the web flow answers with redirects and flashed errors, never JSON, and
 * must not reveal whether the e-mail exists or the account is inactive.
 */
class LoginRequest extends FormRequest
{
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
     * The attempt spends the same budget as the API login (ADR-0013).
     *
     * Valid credentials without access to the admin area get no session and
     * a message of their own (Phase 4.2). It reveals nothing new: the same
     * credentials already succeed on POST /api/v1/auth/login.
     *
     * @throws ValidationException
     * @throws TooManyLoginAttemptsException
     */
    public function authenticate(): void
    {
        $user = VerifyLoginCredentials::execute(
            $this->string('email')->toString(),
            $this->string('password')->toString(),
            $this,
        );

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => 'Credenciais inválidas.',
            ]);
        }

        // Checked before logging in, so a refused account never holds a
        // session, not even for the rest of this request.
        if (! AdminNavigation::allows($user)) {
            throw ValidationException::withMessages([
                'email' => 'Esta conta não tem acesso à área administrativa.',
            ]);
        }

        Auth::guard('web')->login($user);
    }
}
