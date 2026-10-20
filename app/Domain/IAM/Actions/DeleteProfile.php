<?php

namespace App\Domain\IAM\Actions;

use App\Domain\IAM\AuditContext;
use App\Domain\IAM\Exceptions\ResourceInUseException;
use App\Events\ProfileDeleted;
use App\Models\Profile;
use Illuminate\Support\Facades\DB;

/**
 * Deletes a profile nobody holds (INV-05). The Policy refuses a profile with
 * users; this checks it again with the row locked, because user_profiles
 * cascades: an assignment made between the Policy and the delete would be
 * removed silently. Assigning a profile references its row, so the lock
 * makes the two operations wait for each other.
 */
class DeleteProfile
{
    public static function execute(Profile $profile, AuditContext $context): void
    {
        DB::transaction(function () use ($profile, $context) {
            Profile::whereKey($profile->getKey())->lockForUpdate()->first();

            if ($profile->users()->exists()) {
                throw ResourceInUseException::profileHasUsers();
            }

            $profile->delete();

            event(new ProfileDeleted($profile, $context));
        });
    }
}
