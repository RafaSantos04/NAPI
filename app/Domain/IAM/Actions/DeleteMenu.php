<?php

namespace App\Domain\IAM\Actions;

use App\Domain\IAM\AuditContext;
use App\Domain\IAM\Exceptions\ResourceInUseException;
use App\Events\MenuDeleted;
use App\Models\Menu;
use Illuminate\Support\Facades\DB;

/**
 * Deletes a menu without submenus (INV-06). The Policy refuses system menus
 * and menus with children; this checks the children again with the row
 * locked, because menus.parent_id cascades: a submenu created between the
 * Policy and the delete would be removed silently, with its matrix rows.
 */
class DeleteMenu
{
    public static function execute(Menu $menu, AuditContext $context): void
    {
        DB::transaction(function () use ($menu, $context) {
            Menu::whereKey($menu->getKey())->lockForUpdate()->first();

            if ($menu->children()->exists()) {
                throw ResourceInUseException::menuHasChildren();
            }

            $menu->delete();

            event(new MenuDeleted($menu, $context));
        });
    }
}
