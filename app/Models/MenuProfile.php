<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property bool $can_view
 * @property bool $can_create
 * @property bool $can_update
 * @property bool $can_delete
 */
#[Fillable(['menu_id', 'profile_id', 'can_view', 'can_create', 'can_update', 'can_delete'])]
class MenuProfile extends Pivot
{
    /**
     * Actions a matrix row can grant. A permission is "{menu key}.{action}".
     *
     * @var array<int, string>
     */
    public const ACTIONS = ['view', 'create', 'update', 'delete'];

    protected $table = 'menu_profiles';

    protected function casts(): array
    {
        return [
            'can_view' => 'boolean',
            'can_create' => 'boolean',
            'can_update' => 'boolean',
            'can_delete' => 'boolean',
        ];
    }

    public function grants(string $action): bool
    {
        return in_array($action, self::ACTIONS, true)
            && $this->getAttribute("can_{$action}") === true;
    }
}
