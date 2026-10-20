<?php

namespace App\Domain\IAM\Actions;

use App\Domain\IAM\AuditContext;
use App\DTOs\SyncMenuPermissionsDto;
use App\DTOs\SyncMenuProfilesDto;
use App\Models\Menu;
use App\Models\Profile;
use Illuminate\Support\Facades\DB;

/**
 * The matrix seen from a menu (Phase 4.4): sets this menu's row in several
 * profiles at once. Each changed profile goes through SyncMenuPermissions
 * with its whole resulting matrix, so the write and the audit entry
 * (`permission_changed`, one per profile) are exactly what the profile page
 * and the API produce. Profiles whose row does not change are not touched.
 *
 * Authorization is SyncMenuProfilesRequest's, which asks
 * ProfilePolicy::syncMenus for every profile that changes.
 */
class SyncMenuProfiles
{
    public static function execute(Menu $menu, SyncMenuProfilesDto $dto, AuditContext $context): void
    {
        // One transaction for all profiles: either every row changes or none.
        DB::transaction(function () use ($menu, $dto, $context) {
            $profiles = Profile::whereKey(array_keys($dto->profiles))->with('menus')->get();

            foreach ($profiles as $profile) {
                $matrix = $profile->matrixWith($menu, $dto->profiles[$profile->id]);

                if ($matrix !== $profile->matrix()) {
                    SyncMenuPermissions::execute($profile, new SyncMenuPermissionsDto($matrix), $context);
                }
            }
        });
    }
}
