<?php

namespace App\Models;

use Database\Factories\ApiSpecificationVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One successful import, append-only in the database (ADR-0014): assessments
 * point at it, so what they evaluated never changes.
 */
// Tenant, specification and importer are not fillable: the import takes them
// from the route and the actor. The document is the client's confidential
// data, so it never leaves through serialization by accident.
#[Fillable(['number', 'format', 'openapi_version', 'info_title', 'info_version', 'content_sha256', 'size_bytes', 'raw_document'])]
#[Hidden(['raw_document'])]
class ApiSpecificationVersion extends Model
{
    /** @use HasFactory<ApiSpecificationVersionFactory> */
    use HasFactory, HasUlids;

    // Created once, never updated.
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'size_bytes' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<ApiSpecification, $this>
     */
    public function specification(): BelongsTo
    {
        return $this->belongsTo(ApiSpecification::class, 'api_specification_id');
    }

    /**
     * @return BelongsTo<OrganizationMembership, $this>
     */
    public function importedBy(): BelongsTo
    {
        return $this->belongsTo(OrganizationMembership::class, 'imported_by_membership_id');
    }

    /**
     * @return HasMany<ApiEndpoint, $this>
     */
    public function endpoints(): HasMany
    {
        return $this->hasMany(ApiEndpoint::class);
    }
}
