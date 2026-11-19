<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApiEndpointIndexRequest;
use App\Http\Resources\ApiEndpointResource;
use App\Models\ApiSpecification;
use App\Models\ApiSpecificationVersion;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;

/**
 * The endpoint inventory of one imported version (ADR-0014, decision 6).
 */
class ApiEndpointController extends Controller
{
    // Fixed, like every listing (ADR-0013), but larger: a contract can hold
    // 2,000 operations, and 15 per page would take more requests than the
    // per-user limit of 120 a minute allows.
    private const PER_PAGE = 100;

    /**
     * Display a listing of the resource.
     */
    public function index(
        ApiEndpointIndexRequest $request,
        Organization $organization,
        ApiSpecification $apiSpecification,
        ApiSpecificationVersion $version,
    ): JsonResponse {
        $endpoints = $version->endpoints()
            ->when($request->validated('method'), fn ($query, string $method) => $query->where('method', $method))
            ->pathOrOperationContains($request->validated('search'))
            ->orderBy('path')
            ->orderBy('method')
            ->orderBy('id')
            ->paginate(self::PER_PAGE);

        return response()->json([
            'data' => ApiEndpointResource::collection($endpoints),
            'meta' => [
                'total' => $endpoints->total(),
                'per_page' => $endpoints->perPage(),
                'current_page' => $endpoints->currentPage(),
            ],
        ]);
    }
}
