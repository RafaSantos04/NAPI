<?php

namespace App\Models;

use Database\Factories\ApiEndpointFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One operation (method × path) of a version's inventory: a projection of
 * the version, rebuildable from its raw document and append-only like it
 * (ADR-0014, decision 6).
 */
// Tenant and version are not fillable: the import takes them from the
// version it is writing.
#[Fillable(['method', 'path', 'operation_id', 'summary'])]
class ApiEndpoint extends Model
{
    /** @use HasFactory<ApiEndpointFactory> */
    use HasFactory, HasUlids;

    // Written once with its version; the version's created_at dates it.
    public $timestamps = false;

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<ApiSpecificationVersion, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(ApiSpecificationVersion::class, 'api_specification_version_id');
    }
}
