<?php

namespace App\Models;

use App\Domain\Projects\Enums\EnvironmentClassification;
use Database\Factories\EnvironmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Tenant, application and responsible are not fillable: the Action takes the
// first two from the route and checks the responsible membership under lock.
#[Fillable(['name', 'slug', 'classification', 'authorized_scope', 'authorization_reference'])]
class Environment extends Model
{
    /** @use HasFactory<EnvironmentFactory> */
    use HasFactory, HasUlids;

    protected function casts(): array
    {
        return [
            'classification' => EnvironmentClassification::class,
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
     * @return BelongsTo<OrganizationMembership, $this>
     */
    public function responsible(): BelongsTo
    {
        return $this->belongsTo(OrganizationMembership::class, 'responsible_membership_id');
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }
}
