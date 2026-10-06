<?php

namespace App\Http\Requests\Web\Admin\User;

use App\Http\Requests\AssignProfileRequest;
use App\Http\Requests\Web\Admin\Concerns\AdminValidationMessages;

class AssignProfilesRequest extends AssignProfileRequest
{
    use AdminValidationMessages;

    /**
     * A form with every checkbox cleared sends no profile_ids at all. Runs
     * before authorize(), so the Policy sees the same empty set the Action
     * will apply.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->has('profile_ids')) {
            $this->merge(['profile_ids' => []]);
        }
    }
}
