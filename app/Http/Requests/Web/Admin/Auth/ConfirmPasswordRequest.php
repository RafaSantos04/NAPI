<?php

namespace App\Http\Requests\Web\Admin\Auth;

use App\Domain\IAM\Actions\VerifyLoginCredentials;
use App\Domain\IAM\Exceptions\TooManyLoginAttemptsException;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

/**
 * Password confirmation before sensitive admin actions (password.confirm,
 * ADR-0013 item 14). The password goes through the same Action and counters
 * as both logins, so confirming is no side door for guessing it.
 */
class ConfirmPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'password.required' => 'Informe a senha.',
            'password.max' => 'A senha deve ter no máximo :max caracteres.',
        ];
    }

    /**
     * @throws ValidationException
     * @throws TooManyLoginAttemptsException
     */
    public function confirm(): void
    {
        /** @var User $user */
        $user = $this->user();

        $verified = VerifyLoginCredentials::execute($user->email, $this->string('password')->toString(), $this);

        if (! $verified?->is($user)) {
            throw ValidationException::withMessages([
                'password' => 'Senha incorreta.',
            ]);
        }
    }
}
