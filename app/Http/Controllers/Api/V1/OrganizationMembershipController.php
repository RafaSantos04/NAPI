<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\IAM\AuditContext;
use App\Domain\Tenancy\Actions\AddOrganizationMember;
use App\Domain\Tenancy\Actions\RemoveOrganizationMember;
use App\Domain\Tenancy\Actions\UpdateOrganizationMembership;
use App\DTOs\AddOrganizationMemberDto;
use App\DTOs\UpdateOrganizationMembershipDto;
use App\Http\Controllers\Controller;
use App\Http\Requests\OrganizationMembershipStoreRequest;
use App\Http\Requests\OrganizationMembershipUpdateRequest;
use App\Http\Resources\OrganizationMembershipResource;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationMembershipController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Organization $organization): JsonResponse
    {
        $this->authorize('viewAny', [OrganizationMembership::class, $organization]);

        $memberships = $organization->memberships()
            ->with('user')
            ->orderBy('created_at')
            ->orderBy('id')
            ->paginate(15);

        return response()->json([
            'data' => OrganizationMembershipResource::collection($memberships),
            'meta' => [
                'total' => $memberships->total(),
                'per_page' => $memberships->perPage(),
                'current_page' => $memberships->currentPage(),
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(OrganizationMembershipStoreRequest $request, Organization $organization): JsonResponse
    {
        $membership = AddOrganizationMember::execute(
            $organization,
            AddOrganizationMemberDto::from($request),
            AuditContext::fromRequest($request),
        );

        return response()->json(OrganizationMembershipResource::make($membership->load('user')), 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(
        OrganizationMembershipUpdateRequest $request,
        Organization $organization,
        OrganizationMembership $membership,
    ): OrganizationMembershipResource {
        UpdateOrganizationMembership::execute(
            $organization,
            $membership,
            UpdateOrganizationMembershipDto::from($request),
            AuditContext::fromRequest($request),
        );

        return OrganizationMembershipResource::make($membership->refresh()->load('user'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(
        Request $request,
        Organization $organization,
        OrganizationMembership $membership,
    ): JsonResponse {
        $this->authorize('delete', [$membership, $organization]);

        RemoveOrganizationMember::execute($organization, $membership, AuditContext::fromRequest($request));

        return response()->json(['message' => 'Organization membership removed.']);
    }
}
