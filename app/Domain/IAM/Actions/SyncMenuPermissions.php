<?php

namespace App\Domain\IAM\Actions;

use App\DTOs\SyncMenuPermissionsDto;
use App\Events\PermissionChanged;
use App\Models\Profile;

class SyncMenuPermissions
{
    public static function execute(Profile $profile, SyncMenuPermissionsDto $dto): void
    {
        $syncData = [];

        foreach ($dto->permissions as $menuId => $permissions) {
            $syncData[$menuId] = [
                'can_view' => $permissions['can_view'],
                'can_create' => $permissions['can_create'],
                'can_update' => $permissions['can_update'],
                'can_delete' => $permissions['can_delete'],
            ];
        }

        $profile->menus()->sync($syncData);

        event(new PermissionChanged($profile, $syncData, request()->ip()));
    }
}
