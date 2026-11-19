<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\ApiInventory\Actions\ImportApiSpecificationVersion;
use App\Domain\IAM\AuditContext;
use App\Http\Controllers\Controller;
use App\Http\Resources\ApiSpecificationVersionResource;
use App\Models\ApiSpecification;
use App\Models\ApiSpecificationVersion;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;

/**
 * Versions are shallow under the organization, bound to their specification
 * (ADR-0014, decision 8). The import takes the document as the raw body.
 */
class ApiSpecificationVersionController extends Controller
{
    // The format comes from the Content-Type, never from a file name
    // (ADR-0014, decision 12).
    private const FORMATS = [
        'application/json' => 'json',
        'application/yaml' => 'yaml',
        'application/x-yaml' => 'yaml',
        'text/yaml' => 'yaml',
    ];

    /**
     * Display a listing of the resource.
     */
    public function index(Organization $organization, ApiSpecification $apiSpecification): JsonResponse
    {
        $this->authorize('view', [$apiSpecification, $organization]);

        $versions = $apiSpecification->versions()
            ->withCount('endpoints')
            ->orderByDesc('number')
            ->paginate(15);

        return response()->json([
            'data' => ApiSpecificationVersionResource::collection($versions),
            'meta' => [
                'total' => $versions->total(),
                'per_page' => $versions->perPage(),
                'current_page' => $versions->currentPage(),
            ],
        ]);
    }

    /**
     * Import a document as a new version, or return the version that already
     * has the same content.
     */
    public function store(Request $request, Organization $organization, ApiSpecification $apiSpecification): JsonResponse
    {
        $this->authorize('import', [$apiSpecification, $organization]);

        $mediaType = strtolower(trim(explode(';', (string) $request->header('Content-Type'))[0]));
        $format = self::FORMATS[$mediaType]
            ?? throw new UnsupportedMediaTypeHttpException('Send the document as application/json or application/yaml.');

        /** @var User $actor */
        $actor = $request->user();
        // Not $request->json() or a FormRequest: those would decode the
        // document outside the parser's limits.
        $version = ImportApiSpecificationVersion::execute(
            $organization,
            $apiSpecification,
            $actor,
            $request->getContent(),
            $format,
            AuditContext::fromRequest($request),
        );

        return ApiSpecificationVersionResource::make($version->loadCount('endpoints'))
            ->response()
            ->setStatusCode($version->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Display the specified resource.
     */
    public function show(Organization $organization, ApiSpecification $apiSpecification, ApiSpecificationVersion $version): ApiSpecificationVersionResource
    {
        $this->authorize('view', [$apiSpecification, $organization]);

        return ApiSpecificationVersionResource::make($version->loadCount('endpoints'));
    }
}
