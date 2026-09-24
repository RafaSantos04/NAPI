<?php

namespace App\Domain\IAM\Actions;

use App\DTOs\AssignProfilesToUserDto;
use App\Events\ProfileAssigned;
use App\Models\User;

class AssignProfilesToUser
{
    public static function execute(User $user, AssignProfilesToUserDto $dto): void
    {
        $adminProfile = $user->profiles()
            ->where('slug', 'admin')
            ->first();

        if ($adminProfile && ! in_array($adminProfile->id, $dto->profile_ids)) {
            $remainingAdmins = User::whereHas('profiles', fn ($q) => $q->where('slug', 'admin'))
                ->where('id', '!=', $user->id)
                ->count();

            if ($remainingAdmins === 0) {
                throw new \Exception('Cannot remove the last admin.');
            }
        }

        $user->profiles()->sync($dto->profile_ids);

        event(new ProfileAssigned($user, $dto->profile_ids, request()->ip()));
    }
}
