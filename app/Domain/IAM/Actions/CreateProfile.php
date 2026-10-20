<?php

namespace App\Domain\IAM\Actions;

use App\Domain\IAM\AuditContext;
use App\DTOs\CreateProfileDto;
use App\Events\ProfileCreated;
use App\Models\Profile;
use Illuminate\Support\Facades\DB;

/**
 * Shared by the API and the admin area (Phase 4.3). A new profile is never
 * a system profile and starts with an empty matrix: it grants nothing until
 * permissions are set through SyncMenuPermissions.
 */
class CreateProfile
{
    public static function execute(CreateProfileDto $dto, AuditContext $context): Profile
    {
        return DB::transaction(function () use ($dto, $context) {
            $profile = Profile::create([
                'name' => $dto->name,
                'slug' => $dto->slug,
                'description' => $dto->description,
            ]);

            event(new ProfileCreated($profile, $context));

            return $profile;
        });
    }
}
