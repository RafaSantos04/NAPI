<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\IAM\AuditContext;
use App\Domain\Tenancy\Actions\CreateOrganization;
use App\Domain\Tenancy\Actions\UpdateOrganization;
use App\Domain\Tenancy\Enums\OrganizationMembershipStatus;
use App\DTOs\CreateOrganizationDto;
use App\DTOs\UpdateOrganizationDto;
use App\Http\Controllers\Controller;
use App\Http\Requests\OrganizationStoreRequest;
use App\Http\Requests\OrganizationUpdateRequest;
use App\Http\Resources\OrganizationResource;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Organization::class);

        $organizations = Organization::query()
            ->whereHas('memberships', fn ($query) => $query
                ->where('user_id', $request->user()->id)
                ->where('status', OrganizationMembershipStatus::Active))
            ->with(['memberships' => fn ($query) => $query
                ->where('user_id', $request->user()->id)
                ->where('status', OrganizationMembershipStatus::Active)])
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(15);

        return response()->json([
            'data' => OrganizationResource::collection($organizations),
            'meta' => [
                'total' => $organizations->total(),
                'per_page' => $organizations->perPage(),
                'current_page' => $organizations->currentPage(),
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(OrganizationStoreRequest $request): JsonResponse
    {
        $organization = CreateOrganization::execute(
            CreateOrganizationDto::from($request),
            $request->user(),
            AuditContext::fromRequest($request),
        );

        return response()->json(OrganizationResource::make($organization), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Organization $organization): OrganizationResource
    {
        $this->authorize('view', $organization);

        $organization->load(['memberships' => fn ($query) => $query
            ->where('user_id', $request->user()->id)
            ->where('status', OrganizationMembershipStatus::Active)]);

        return OrganizationResource::make($organization);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(OrganizationUpdateRequest $request, Organization $organization): OrganizationResource
    {
        UpdateOrganization::execute(
            $organization,
            UpdateOrganizationDto::from($request),
            AuditContext::fromRequest($request),
        );

        return OrganizationResource::make($organization);
    }
}
