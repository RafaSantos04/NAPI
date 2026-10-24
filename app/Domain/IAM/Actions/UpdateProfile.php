<?php

namespace App\Domain\IAM\Actions;

use App\Domain\IAM\AuditContext;
use App\DTOs\UpdateProfileDto;
use App\Events\ProfileUpdated;
use App\Models\Profile;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Shared by the API and the admin area. Audits the names of the fields that
 * actually changed; a no-op update records nothing. Whether the profile may
 * be changed at all (system profiles may not) is the Policy's answer.
 */
class UpdateProfile
{
    public static function execute(Profile $profile, UpdateProfileDto $dto, AuditContext $context): Profile
    {
        // Second line behind ProfileUpdateRequest: the administrator profile
        // is found by its slug and always stays a system profile (INV-08).
        if ($profile->isAdministrator()
            && (($dto->attributes['slug'] ?? Profile::ADMIN) !== Profile::ADMIN || ($dto->attributes['is_system'] ?? true) == false)) {
            throw new LogicException('The administrator profile keeps its identifier and stays a system profile.');
        }

        DB::transaction(function () use ($profile, $dto, $context) {
            $profile->update($dto->attributes);

            $fields = array_values(array_diff(array_keys($profile->getChanges()), [$profile->getUpdatedAtColumn()]));

            if ($fields !== []) {
                event(new ProfileUpdated($profile, $fields, $context));
            }
        });

        return $profile;
    }
}
