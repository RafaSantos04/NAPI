<?php

namespace App\Http\Requests\Web\Admin\Menu;

use App\Http\Requests\MenuUpdateRequest;
use App\Http\Requests\Web\Admin\Concerns\AdminValidationMessages;
use App\Http\Requests\Web\Admin\Concerns\SendsEveryField;

class UpdateMenuRequest extends MenuUpdateRequest
{
    use AdminValidationMessages, SendsEveryField;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->everyFieldRequired(parent::rules());
    }

    protected function cycleMessage(): string
    {
        return 'Um menu não pode ficar abaixo de si mesmo nem de um dos seus submenus.';
    }
}
