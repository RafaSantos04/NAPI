<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\IAM\AuditContext;
use App\Domain\Projects\Actions\ArchiveApplication;
use App\Domain\Projects\Actions\RestoreApplication;
use App\Http\Controllers\Controller;
use App\Http\Resources\ApplicationResource;
use App\Models\Application;
use App\Models\Organization;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Archiving as explicit operations, like ProjectStatusController: who may
 * archive depends on who created the parent project (ADR-0012).
 */
class ApplicationStatusController extends Controller
{
    public function archive(Request $request, Organization $organization, Project $project, Application $application): JsonResponse
    {
        $this->authorize('archive', [$application, $project, $organization]);

        ArchiveApplication::execute($organization, $project, $application, AuditContext::fromRequest($request));

        return response()->json([
            'message' => 'Application archived.',
            'data' => ApplicationResource::make($application->refresh()),
        ]);
    }

    public function restore(Request $request, Organization $organization, Project $project, Application $application): JsonResponse
    {
        $this->authorize('restore', [$application, $project, $organization]);

        RestoreApplication::execute($organization, $project, $application, AuditContext::fromRequest($request));

        return response()->json([
            'message' => 'Application restored.',
            'data' => ApplicationResource::make($application->refresh()),
        ]);
    }
}
