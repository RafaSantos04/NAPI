<?php

namespace App\Models;

use App\Models\Concerns\Archivable;
use App\Models\Concerns\SearchableByName;
use Database\Factories\ApiSpecificationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The logical contract of an application, like "public API" (ADR-0014).
 * Each successful import becomes an immutable ApiSpecificationVersion.
 */
// Tenant and application are not fillable: the Action takes both from the
// route, never from the payload.
#[Fillable(['name', 'slug', 'description'])]
class ApiSpecification extends Model
{
    /** @use HasFactory<ApiSpecificationFactory> */
    use Archivable, HasFactory, HasUlids, SearchableByName;

    protected function casts(): array
    {
        return [
            'archived_at' => 'immutable_datetime',
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
     * @return BelongsTo<Application, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /**
     * @return HasMany<ApiSpecificationVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(ApiSpecificationVersion::class);
    }
}
