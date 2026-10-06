<?php

namespace App\Http\Requests\Web\Admin\User;

use App\Http\Requests\UserStoreRequest;
use App\Http\Requests\Web\Admin\Concerns\AdminValidationMessages;

class StoreUserRequest extends UserStoreRequest
{
    use AdminValidationMessages;
}
