<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\ApiInventory\Actions\ArchiveApiSpecification;
use App\Domain\ApiInventory\Actions\RestoreApiSpecification;
use App\Domain\IAM\AuditContext;
use App\Http\Controllers\Controller;
use App\Http\Resources\ApiSpecificationResource;
use App\Models\ApiSpecification;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Archiving as explicit operations, like the rest of the tree: who may
 * archive depends on who created the ancestor project (ADR-0012).
 */
class ApiSpecificationStatusController extends Controller
{
    public function archive(Request $request, Organization $organization, ApiSpecification $apiSpecification): JsonResponse
    {
        $this->authorize('archive', [$apiSpecification, $organization]);

        ArchiveApiSpecification::execute($organization, $apiSpecification, AuditContext::fromRequest($request));

        return response()->json([
            'message' => 'API specification archived.',
            'data' => ApiSpecificationResource::make($apiSpecification->refresh()),
        ]);
    }

    public function restore(Request $request, Organization $organization, ApiSpecification $apiSpecification): JsonResponse
    {
        $this->authorize('restore', [$apiSpecification, $organization]);

        RestoreApiSpecification::execute($organization, $apiSpecification, AuditContext::fromRequest($request));

        return response()->json([
            'message' => 'API specification restored.',
            'data' => ApiSpecificationResource::make($apiSpecification->refresh()),
        ]);
    }
}
