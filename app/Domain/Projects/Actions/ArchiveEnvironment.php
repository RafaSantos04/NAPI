<?php

namespace App\Domain\Projects\Actions;

use App\Domain\IAM\AuditContext;
use App\Domain\Projects\Exceptions\EnvironmentLifecycleException;
use App\Domain\Projects\ProjectTreeLock;
use App\Events\EnvironmentArchived;
use App\Models\Application;
use App\Models\Environment;
use App\Models\Organization;
use Illuminate\Support\Facades\DB;

class ArchiveEnvironment
{
    public static function execute(
        Organization $organization,
        Application $application,
        Environment $environment,
        AuditContext $context,
    ): void {
        DB::transaction(function () use ($organization, $application, $environment, $context) {
            $lockedApplication = ProjectTreeLock::activeApplication($organization, $application);

            $locked = Environment::query()
                ->whereKey($environment->id)
                ->whereBelongsTo($lockedApplication)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->isArchived()) {
                throw EnvironmentLifecycleException::archived($locked);
            }

            $locked->archived_at = now()->toImmutable();
            $locked->save();

            event(new EnvironmentArchived($locked, $context->forOrganization($organization->id)));
        });
    }
}
