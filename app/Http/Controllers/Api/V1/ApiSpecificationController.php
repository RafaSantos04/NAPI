<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\ApiInventory\Actions\CreateApiSpecification;
use App\Domain\ApiInventory\Actions\UpdateApiSpecification;
use App\Domain\IAM\AuditContext;
use App\DTOs\CreateApiSpecificationDto;
use App\DTOs\UpdateApiSpecificationDto;
use App\Http\Controllers\Controller;
use App\Http\Requests\ApiSpecificationIndexRequest;
use App\Http\Requests\ApiSpecificationStoreRequest;
use App\Http\Requests\ApiSpecificationUpdateRequest;
use App\Http\Resources\ApiSpecificationResource;
use App\Models\ApiSpecification;
use App\Models\Application;
use App\Models\Organization;
use App\Models\Project;
use Illuminate\Http\JsonResponse;

/**
 * Listing and creating need the parent application; the rest of the routes
 * are shallow, under the organization (ADR-0014, decision 8).
 */
class ApiSpecificationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(
        ApiSpecificationIndexRequest $request,
        Organization $organization,
        Project $project,
        Application $application,
    ): JsonResponse {
        $specifications = $application->apiSpecifications()
            ->withStatus($request->validated('status', 'active'))
            ->nameContains($request->validated('search'))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(15);

        return response()->json([
            'data' => ApiSpecificationResource::collection($specifications),
            'meta' => [
                'total' => $specifications->total(),
                'per_page' => $specifications->perPage(),
                'current_page' => $specifications->currentPage(),
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(
        ApiSpecificationStoreRequest $request,
        Organization $organization,
        Project $project,
        Application $application,
    ): JsonResponse {
        $specification = CreateApiSpecification::execute(
            $organization,
            $application,
            CreateApiSpecificationDto::from($request),
            AuditContext::fromRequest($request),
        );

        return ApiSpecificationResource::make($specification)->response()->setStatusCode(201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Organization $organization, ApiSpecification $apiSpecification): ApiSpecificationResource
    {
        $this->authorize('view', [$apiSpecification, $organization]);

        return ApiSpecificationResource::make($apiSpecification);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(
        ApiSpecificationUpdateRequest $request,
        Organization $organization,
        ApiSpecification $apiSpecification,
    ): ApiSpecificationResource {
        UpdateApiSpecification::execute(
            $organization,
            $apiSpecification,
            UpdateApiSpecificationDto::from($request),
            AuditContext::fromRequest($request),
        );

        return ApiSpecificationResource::make($apiSpecification->refresh());
    }
}
