<?php

namespace App\Http\Requests\Web\Admin\Profile;

use App\Http\Requests\ProfileStoreRequest;
use App\Http\Requests\Web\Admin\Concerns\AdminValidationMessages;

class StoreProfileRequest extends ProfileStoreRequest
{
    use AdminValidationMessages;
}
