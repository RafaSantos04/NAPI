<?php

namespace App\Http\Requests\Web\Admin\Profile;

use App\Http\Requests\SyncMenusRequest;
use App\Http\Requests\Web\Admin\Concerns\AdminValidationMessages;

/**
 * The matrix form posts every cell: a hidden "0" followed by the checkbox,
 * which overrides it with "1" when checked. Rules and authorization (anti
 * escalation included) are the API's: SyncMenusRequest and
 * ProfilePolicy::syncMenus.
 */
class SyncPermissionsRequest extends SyncMenusRequest
{
    use AdminValidationMessages;

    /**
     * Runs before authorize(), so the Policy sees the same set the Action
     * will apply.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->has('permissions')) {
            $this->merge(['permissions' => []]);
        }
    }
}
