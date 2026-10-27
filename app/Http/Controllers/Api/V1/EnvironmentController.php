<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\IAM\AuditContext;
use App\Domain\Projects\Actions\CreateEnvironment;
use App\Domain\Projects\Actions\UpdateEnvironment;
use App\DTOs\CreateEnvironmentDto;
use App\DTOs\UpdateEnvironmentDto;
use App\Http\Controllers\Controller;
use App\Http\Requests\EnvironmentIndexRequest;
use App\Http\Requests\EnvironmentStoreRequest;
use App\Http\Requests\EnvironmentUpdateRequest;
use App\Http\Resources\EnvironmentResource;
use App\Models\Application;
use App\Models\Environment;
use App\Models\Organization;
use App\Models\Project;
use Illuminate\Http\JsonResponse;

class EnvironmentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(
        EnvironmentIndexRequest $request,
        Organization $organization,
        Project $project,
        Application $application,
    ): JsonResponse {
        $status = $request->validated('status', 'active');
        $search = $request->validated('search');
        $classification = $request->validated('classification');

        $environments = $application->environments()
            ->when($status === 'active', fn ($query) => $query->whereNull('archived_at'))
            ->when($status === 'archived', fn ($query) => $query->whereNotNull('archived_at'))
            ->when($classification !== null, fn ($query) => $query->where('classification', $classification))
            // Wildcards typed by the client are matched literally.
            ->when($search !== null, fn ($query) => $query->whereLike(
                'name',
                '%'.addcslashes($search, '\\%_').'%',
            ))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(15);

        return response()->json([
            'data' => EnvironmentResource::collection($environments),
            'meta' => [
                'total' => $environments->total(),
                'per_page' => $environments->perPage(),
                'current_page' => $environments->currentPage(),
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(
        EnvironmentStoreRequest $request,
        Organization $organization,
        Project $project,
        Application $application,
    ): JsonResponse {
        $environment = CreateEnvironment::execute(
            $organization,
            $project,
            $application,
            CreateEnvironmentDto::from($request),
            $request->user(),
            AuditContext::fromRequest($request),
        );

        return EnvironmentResource::make($environment)->response()->setStatusCode(201);
    }

    /**
     * Display the specified resource.
     */
    public function show(
        Organization $organization,
        Project $project,
        Application $application,
        Environment $environment,
    ): EnvironmentResource {
        $this->authorize('view', [$environment, $application, $project, $organization]);

        return EnvironmentResource::make($environment);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(
        EnvironmentUpdateRequest $request,
        Organization $organization,
        Project $project,
        Application $application,
        Environment $environment,
    ): EnvironmentResource {
        UpdateEnvironment::execute(
            $organization,
            $project,
            $application,
            $environment,
            UpdateEnvironmentDto::from($request),
            AuditContext::fromRequest($request),
        );

        return EnvironmentResource::make($environment->refresh());
    }
}
