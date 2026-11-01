<?php

namespace App\Models;

use App\Models\Concerns\Archivable;
use App\Models\Concerns\SearchableByName;
use Database\Factories\ApplicationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Tenant and project are not fillable: the Action takes both from the route,
// never from the payload.
#[Fillable(['name', 'slug', 'description'])]
class Application extends Model
{
    /** @use HasFactory<ApplicationFactory> */
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
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Also scopes the {environment} route binding to this application.
     *
     * @return HasMany<Environment, $this>
     */
    public function environments(): HasMany
    {
        return $this->hasMany(Environment::class);
    }

    /**
     * The contracts of this application (ADR-0014).
     *
     * @return HasMany<ApiSpecification, $this>
     */
    public function apiSpecifications(): HasMany
    {
        return $this->hasMany(ApiSpecification::class);
    }
}
