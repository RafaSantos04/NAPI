<?php

namespace App\Domain\IAM\Actions;

use App\Domain\IAM\AuditContext;
use App\DTOs\SyncMenuPermissionsDto;
use App\Events\PermissionChanged;
use App\Models\Profile;
use Illuminate\Support\Facades\DB;

class SyncMenuPermissions
{
    public static function execute(Profile $profile, SyncMenuPermissionsDto $dto, AuditContext $context): void
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

        DB::transaction(function () use ($profile, $syncData, $context) {
            $profile->menus()->sync($syncData);

            event(new PermissionChanged($profile, $syncData, $context));
        });
    }
}
