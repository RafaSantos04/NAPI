<?php

namespace App\Domain\IAM\Actions;

use App\Domain\IAM\AuditContext;
use App\DTOs\CreateMenuDto;
use App\Events\MenuCreated;
use App\Models\Menu;
use Illuminate\Support\Facades\DB;

/**
 * Shared by the API and the admin area (Phase 4.4). A new menu is never a
 * system menu. Its key becomes a permission area at once: the admin profile
 * holds it implicitly, and everyone else gets it through the matrix.
 */
class CreateMenu
{
    public static function execute(CreateMenuDto $dto, AuditContext $context): Menu
    {
        return DB::transaction(function () use ($dto, $context) {
            $menu = Menu::create($dto->attributes());

            event(new MenuCreated($menu, $context));

            return $menu;
        });
    }
}
