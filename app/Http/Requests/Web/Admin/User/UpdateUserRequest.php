<?php

namespace App\Http\Requests\Web\Admin\User;

use App\Http\Requests\UserUpdateRequest;
use App\Http\Requests\Web\Admin\Concerns\AdminValidationMessages;
use App\Http\Requests\Web\Admin\Concerns\SendsEveryField;

class UpdateUserRequest extends UserUpdateRequest
{
    use AdminValidationMessages, SendsEveryField;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->everyFieldRequired(parent::rules());
    }
}
