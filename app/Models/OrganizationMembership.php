<?php

namespace App\Models;

use App\Domain\Tenancy\Enums\OrganizationMembershipStatus;
use App\Domain\Tenancy\Enums\OrganizationPermission;
use App\Domain\Tenancy\Enums\OrganizationRole;
use Database\Factories\OrganizationMembershipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['organization_id', 'user_id', 'role', 'status'])]
class OrganizationMembership extends Model
{
    /** @use HasFactory<OrganizationMembershipFactory> */
    use HasFactory, HasUlids;

    protected function casts(): array
    {
        return [
            'role' => OrganizationRole::class,
            'status' => OrganizationMembershipStatus::class,
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
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->status === OrganizationMembershipStatus::Active;
    }

    public function isOwner(): bool
    {
        return $this->role === OrganizationRole::Owner;
    }

    public function grants(string $permission): bool
    {
        $permission = OrganizationPermission::tryFrom($permission);

        return $this->isActive()
            && $permission !== null
            && $this->role->grants($permission);
    }
}
