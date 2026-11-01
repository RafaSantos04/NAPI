<?php

namespace App\Domain\Projects\Actions;

use App\Domain\IAM\AuditContext;
use App\Domain\Projects\ProjectTreeLock;
use App\Domain\Tenancy\Enums\OrganizationMembershipStatus;
use App\DTOs\CreateEnvironmentDto;
use App\Events\EnvironmentCreated;
use App\Models\Application;
use App\Models\Environment;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateEnvironment
{
    public static function execute(
        Organization $organization,
        Application $application,
        CreateEnvironmentDto $dto,
        User $actor,
        AuditContext $context,
    ): Environment {
        return DB::transaction(function () use ($organization, $application, $dto, $actor, $context) {
            $lockedApplication = ProjectTreeLock::activeApplication($organization, $application);

            $responsibleId = $dto->responsibleMembershipId
                ?? $actor->organizationMemberships()->whereBelongsTo($organization)->value('id');

            // Validation already checked it; the lock keeps the membership
            // from being disabled before this environment commits.
            $responsible = OrganizationMembership::query()
                ->whereKey($responsibleId)
                ->whereBelongsTo($organization)
                ->where('status', OrganizationMembershipStatus::Active)
                ->sharedLock()
                ->first();

            if ($responsible === null) {
                throw ValidationException::withMessages([
                    'responsible_membership_id' => 'The responsible membership must be active in this organization.',
                ]);
            }

            $environment = $lockedApplication->environments()->make([
                'name' => $dto->name,
                'slug' => $dto->slug,
                'classification' => $dto->classification,
                'authorized_scope' => $dto->authorizedScope,
                'authorization_reference' => $dto->authorizationReference,
            ]);
            $environment->organization()->associate($organization);
            $environment->responsible()->associate($responsible);
            $environment->save();

            event(new EnvironmentCreated($environment, $context->forOrganization($organization->id)));

            return $environment;
        });
    }
}
