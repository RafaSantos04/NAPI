<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\IAM\AuditContext;
use App\Domain\Projects\Actions\CreateProject;
use App\Domain\Projects\Actions\UpdateProject;
use App\DTOs\CreateProjectDto;
use App\DTOs\UpdateProjectDto;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProjectIndexRequest;
use App\Http\Requests\ProjectStoreRequest;
use App\Http\Requests\ProjectUpdateRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Organization;
use App\Models\Project;
use Illuminate\Http\JsonResponse;

class ProjectController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(ProjectIndexRequest $request, Organization $organization): JsonResponse
    {
        $status = $request->validated('status', 'active');
        $search = $request->validated('search');

        $projects = $organization->projects()
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
            'data' => ProjectResource::collection($projects),
            'meta' => [
                'total' => $projects->total(),
                'per_page' => $projects->perPage(),
                'current_page' => $projects->currentPage(),
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ProjectStoreRequest $request, Organization $organization): JsonResponse
    {
        $project = CreateProject::execute(
            $organization,
            CreateProjectDto::from($request),
            $request->user(),
            AuditContext::fromRequest($request),
        );

        return ProjectResource::make($project)->response()->setStatusCode(201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Organization $organization, Project $project): ProjectResource
    {
        $this->authorize('view', [$project, $organization]);

        return ProjectResource::make($project);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(ProjectUpdateRequest $request, Organization $organization, Project $project): ProjectResource
    {
        UpdateProject::execute(
            $organization,
            $project,
            UpdateProjectDto::from($request),
            AuditContext::fromRequest($request),
        );

        return ProjectResource::make($project->refresh());
    }
}
