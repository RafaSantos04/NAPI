<?php

namespace App\Http\Requests\Web\Admin\Profile;

use App\Http\Requests\ProfileUpdateRequest;
use App\Http\Requests\Web\Admin\Concerns\AdminValidationMessages;
use App\Http\Requests\Web\Admin\Concerns\SendsEveryField;

class UpdateProfileRequest extends ProfileUpdateRequest
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
