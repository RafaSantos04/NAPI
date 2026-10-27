<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\IAM\AuditContext;
use App\Domain\Projects\Actions\CreateApplication;
use App\Domain\Projects\Actions\UpdateApplication;
use App\DTOs\CreateApplicationDto;
use App\DTOs\UpdateApplicationDto;
use App\Http\Controllers\Controller;
use App\Http\Requests\ApplicationIndexRequest;
use App\Http\Requests\ApplicationStoreRequest;
use App\Http\Requests\ApplicationUpdateRequest;
use App\Http\Resources\ApplicationResource;
use App\Models\Application;
use App\Models\Organization;
use App\Models\Project;
use Illuminate\Http\JsonResponse;

class ApplicationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(ApplicationIndexRequest $request, Organization $organization, Project $project): JsonResponse
    {
        $status = $request->validated('status', 'active');
        $search = $request->validated('search');

        $applications = $project->applications()
            ->when($status === 'active', fn ($query) => $query->whereNull('archived_at'))
            ->when($status === 'archived', fn ($query) => $query->whereNotNull('archived_at'))
            // Wildcards typed by the client are matched literally.
            ->when($search !== null, fn ($query) => $query->whereLike(
                'name',
                '%'.addcslashes($search, '\\%_').'%',
            ))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(15);

        return response()->json([
            'data' => ApplicationResource::collection($applications),
            'meta' => [
                'total' => $applications->total(),
                'per_page' => $applications->perPage(),
                'current_page' => $applications->currentPage(),
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ApplicationStoreRequest $request, Organization $organization, Project $project): JsonResponse
    {
        $application = CreateApplication::execute(
            $organization,
            $project,
            CreateApplicationDto::from($request),
            AuditContext::fromRequest($request),
        );

        return ApplicationResource::make($application)->response()->setStatusCode(201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Organization $organization, Project $project, Application $application): ApplicationResource
    {
        $this->authorize('view', [$application, $project, $organization]);

        return ApplicationResource::make($application);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(
        ApplicationUpdateRequest $request,
        Organization $organization,
        Project $project,
        Application $application,
    ): ApplicationResource {
        UpdateApplication::execute(
            $organization,
            $project,
            $application,
            UpdateApplicationDto::from($request),
            AuditContext::fromRequest($request),
        );

        return ApplicationResource::make($application->refresh());
    }
}
