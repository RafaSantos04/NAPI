<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\IAM\AuditContext;
use App\Domain\Projects\Actions\ArchiveProject;
use App\Domain\Projects\Actions\RestoreProject;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectResource;
use App\Models\Organization;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Archiving as explicit operations, not a field of PATCH: who may archive
 * depends on who created the project (ADR-0012), like account status in
 * UserStatusController.
 */
class ProjectStatusController extends Controller
{
    public function archive(Request $request, Organization $organization, Project $project): JsonResponse
    {
        $this->authorize('archive', [$project, $organization]);

        ArchiveProject::execute($organization, $project, AuditContext::fromRequest($request));

        return response()->json([
            'message' => 'Project archived.',
            'data' => ProjectResource::make($project->refresh()),
        ]);
    }

    public function restore(Request $request, Organization $organization, Project $project): JsonResponse
    {
        $this->authorize('restore', [$project, $organization]);

        RestoreProject::execute($organization, $project, AuditContext::fromRequest($request));

        return response()->json([
            'message' => 'Project restored.',
            'data' => ProjectResource::make($project->refresh()),
        ]);
    }
}
