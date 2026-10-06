<?php

namespace App\Http\Requests\Web\Admin\User;

use App\Http\Requests\UserUpdateRequest;
use App\Http\Requests\Web\Admin\Concerns\AdminValidationMessages;

class UpdateUserRequest extends UserUpdateRequest
{
    use AdminValidationMessages;

    /**
     * The API accepts partial updates; the admin form always sends every
     * field, so an emptied one is an error rather than "leave unchanged".
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_map(
            fn (array $rules) => array_map(fn (mixed $rule) => $rule === 'sometimes' ? 'required' : $rule, $rules),
            parent::rules(),
        );
    }
}
