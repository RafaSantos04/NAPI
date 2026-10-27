<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\IAM\AuditContext;
use App\Domain\Projects\Actions\ArchiveEnvironment;
use App\Domain\Projects\Actions\RestoreEnvironment;
use App\Http\Controllers\Controller;
use App\Http\Resources\EnvironmentResource;
use App\Models\Application;
use App\Models\Environment;
use App\Models\Organization;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Archiving as explicit operations, like ApplicationStatusController: who may
 * archive depends on who created the ancestor project (ADR-0012).
 */
class EnvironmentStatusController extends Controller
{
    public function archive(
        Request $request,
        Organization $organization,
        Project $project,
        Application $application,
        Environment $environment,
    ): JsonResponse {
        $this->authorize('archive', [$environment, $application, $project, $organization]);

        ArchiveEnvironment::execute($organization, $project, $application, $environment, AuditContext::fromRequest($request));

        return response()->json([
            'message' => 'Environment archived.',
            'data' => EnvironmentResource::make($environment->refresh()),
        ]);
    }

    public function restore(
        Request $request,
        Organization $organization,
        Project $project,
        Application $application,
        Environment $environment,
    ): JsonResponse {
        $this->authorize('restore', [$environment, $application, $project, $organization]);

        RestoreEnvironment::execute($organization, $project, $application, $environment, AuditContext::fromRequest($request));

        return response()->json([
            'message' => 'Environment restored.',
            'data' => EnvironmentResource::make($environment->refresh()),
        ]);
    }
}
