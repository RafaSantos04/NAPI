<?php

namespace App\Domain\IAM\Actions;

use App\Domain\IAM\AuditContext;
use App\Events\UserActivated;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Lets an account authenticate again. Nothing from before the deactivation
 * comes back: tokens and sessions were removed by DeactivateUser, and no new
 * token is issued here (Phase 4.2).
 */
class ActivateUser
{
    public static function execute(User $user, AuditContext $context): void
    {
        if ($user->is_active) {
            return;
        }

        DB::transaction(function () use ($user, $context) {
            $user->update(['is_active' => true]);

            event(new UserActivated($user, $context));
        });
    }
}
