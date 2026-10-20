<?php

namespace App\Http\Requests\Web\Admin\Menu;

use App\Http\Requests\MenuStoreRequest;
use App\Http\Requests\Web\Admin\Concerns\AdminValidationMessages;

class StoreMenuRequest extends MenuStoreRequest
{
    use AdminValidationMessages;
}
