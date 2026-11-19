<?php

namespace App\Domain\ApiInventory\Actions;

use App\Domain\ApiInventory\Exceptions\ApiImportInProgressException;
use App\Domain\ApiInventory\Exceptions\ApiSpecificationLifecycleException;
use App\Domain\ApiInventory\Exceptions\OpenApiParseException;
use App\Domain\ApiInventory\OpenApiDocument;
use App\Domain\ApiInventory\OpenApiParser;
use App\Domain\IAM\AuditContext;
use App\Domain\Projects\ProjectTreeLock;
use App\Events\ApiSpecificationImportRejected;
use App\Events\ApiSpecificationVersionImported;
use App\Models\ApiEndpoint;
use App\Models\ApiSpecification;
use App\Models\ApiSpecificationVersion;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Turns an OpenAPI document into an immutable version and its endpoint
 * inventory (ADR-0014, decisions 14 to 20). The caller has authorized the
 * import and checked the body size and format.
 */
class ImportApiSpecificationVersion
{
    // Above the 5 s budget and below max_execution_time, so a crashed
    // request cannot block the organization for long.
    private const LOCK_SECONDS = 30;

    /**
     * Returns the new version, or the existing one when the same content was
     * already imported (`wasRecentlyCreated` tells them apart).
     */
    public static function execute(
        Organization $organization,
        ApiSpecification $specification,
        User $actor,
        string $rawDocument,
        string $format,
        AuditContext $context,
    ): ApiSpecificationVersion {
        $context = $context->forOrganization($organization->id);
        $lock = Cache::lock('api-imports:organization:'.$organization->id, self::LOCK_SECONDS);

        if (! $lock->get()) {
            throw new ApiImportInProgressException($organization);
        }

        try {
            // Parsing runs before the transaction: no row stays locked while
            // the CPU-bound part runs.
            try {
                $document = (new OpenApiParser)->parse($rawDocument, $format);
            } catch (OpenApiParseException $rejection) {
                event(new ApiSpecificationImportRejected($specification, $rejection, $format, $rawDocument, $context));

                throw $rejection;
            }

            return DB::transaction(fn () => self::store($organization, $specification, $actor, $rawDocument, $document, $context));
        } finally {
            $lock->release();
        }
    }

    private static function store(
        Organization $organization,
        ApiSpecification $specification,
        User $actor,
        string $rawDocument,
        OpenApiDocument $document,
        AuditContext $context,
    ): ApiSpecificationVersion {
        $lockedApplication = ProjectTreeLock::activeApplication($organization, $specification->application);

        // FOR UPDATE serializes the numbering and a concurrent archive
        // (ADR-0014, decision 17).
        $locked = ApiSpecification::query()
            ->whereKey($specification->id)
            ->whereBelongsTo($lockedApplication)
            ->lockForUpdate()
            ->firstOrFail();

        if ($locked->isArchived()) {
            throw ApiSpecificationLifecycleException::archived($locked);
        }

        $contentSha256 = hash('sha256', $rawDocument);

        // Same content, same version: no new version and no audit entry
        // (ADR-0013 item 33). Another format is other content.
        $existing = $locked->versions()->where('content_sha256', $contentSha256)->first();
        if ($existing !== null) {
            return $existing;
        }

        $previous = $locked->versions()->orderByDesc('number')->first();

        $version = $locked->versions()->make([
            'number' => ($previous->number ?? 0) + 1,
            'format' => $document->format,
            'openapi_version' => $document->openApiVersion,
            'info_title' => $document->title,
            'info_version' => $document->infoVersion,
            'content_sha256' => $contentSha256,
            'size_bytes' => strlen($rawDocument),
            'raw_document' => $rawDocument,
        ]);
        $version->organization()->associate($organization);
        $version->importedBy()->associate($actor->organizationMemberships()->whereBelongsTo($organization)->sole());
        $version->save();

        if ($document->endpoints !== []) {
            ApiEndpoint::query()->insert(array_map(fn (array $endpoint) => [
                ...$endpoint,
                'id' => (string) Str::ulid(),
                'organization_id' => $organization->id,
                'api_specification_version_id' => $version->id,
            ], $document->endpoints));
        }

        event(new ApiSpecificationVersionImported($version, self::inventoryChange($document, $previous), $context));

        return $version;
    }

    /**
     * The diff against the previous version, by the endpoint's identity
     * (method and path template); the endpoints themselves stay in both
     * versions, so the full diff can always be rebuilt.
     *
     * @return array{endpoints: int, added: int, removed: int}
     */
    private static function inventoryChange(OpenApiDocument $document, ?ApiSpecificationVersion $previous): array
    {
        $identity = fn (string $method, string $path): string => $method.' '.OpenApiParser::pathTemplate($path);

        $current = array_map(fn (array $endpoint) => $identity($endpoint['method'], $endpoint['path']), $document->endpoints);
        $before = $previous === null ? [] : $previous->endpoints()
            ->get(['method', 'path'])
            ->map(fn (ApiEndpoint $endpoint) => $identity($endpoint->method, $endpoint->path))
            ->all();

        return [
            'endpoints' => count($current),
            'added' => count(array_diff($current, $before)),
            'removed' => count(array_diff($before, $current)),
        ];
    }
}
