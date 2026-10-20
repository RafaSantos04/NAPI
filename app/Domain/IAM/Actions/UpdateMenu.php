<?php

namespace App\Domain\IAM\Actions;

use App\Domain\IAM\AuditContext;
use App\DTOs\UpdateMenuDto;
use App\Events\MenuUpdated;
use App\Models\Menu;
use Illuminate\Support\Facades\DB;

/**
 * Shared by the API and the admin area. Only navigation attributes reach
 * it (UpdateMenuDto has no key). Audits the names of the changed fields; a
 * no-op update records nothing.
 */
class UpdateMenu
{
    public static function execute(Menu $menu, UpdateMenuDto $dto, AuditContext $context): Menu
    {
        DB::transaction(function () use ($menu, $dto, $context) {
            $menu->update($dto->attributes);

            $fields = array_values(array_diff(array_keys($menu->getChanges()), [$menu->getUpdatedAtColumn()]));

            if ($fields !== []) {
                event(new MenuUpdated($menu, $fields, $context));
            }
        });

        return $menu;
    }
}
