<?php

namespace App\Domain\IAM\Actions;

use App\Domain\IAM\AuditContext;
use App\Domain\IAM\EnsureActiveAdministratorRemains;
use App\DTOs\AssignProfilesToUserDto;
use App\Events\ProfileAssigned;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AssignProfilesToUser
{
    public static function execute(User $user, AssignProfilesToUserDto $dto, AuditContext $context): void
    {
        DB::transaction(function () use ($user, $dto, $context) {
            $adminProfileId = Profile::where('slug', Profile::ADMIN)->value('id');

            if (! in_array($adminProfileId, $dto->profile_ids, true)) {
                EnsureActiveAdministratorRemains::beforeRemoving($user);
            }

            $user->profiles()->sync(self::withAssigner($user, $dto->profile_ids, $context->actorId));

            event(new ProfileAssigned($user, $dto->profile_ids, $context));
        });
    }

    /**
     * Records who granted each newly attached profile. Profiles the user
     * already had keep their original assigner (FIND-017).
     *
     * @param  array<int, string>  $profileIds
     * @return array<int|string, mixed>
     */
    private static function withAssigner(User $user, array $profileIds, ?string $actorId): array
    {
        $current = $user->profiles()->pluck('profiles.id')->all();
        $sync = [];

        foreach ($profileIds as $profileId) {
            if (in_array($profileId, $current, true)) {
                $sync[] = $profileId;
            } else {
                $sync[$profileId] = ['assigned_by' => $actorId];
            }
        }

        return $sync;
    }
}
