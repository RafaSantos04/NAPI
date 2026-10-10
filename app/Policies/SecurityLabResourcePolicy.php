<?php

namespace App\Policies;

use App\Models\SecurityLabResource;
use App\Models\User;

/**
 * The control the protected scenario of the IDOR test demonstrates: finding
 * a resource by its identifier does not mean the actor may read it. Pure
 * ownership, with no bypass for any profile.
 */
class SecurityLabResourcePolicy
{
    public function view(User $actor, SecurityLabResource $resource): bool
    {
        return $resource->owner_user_id === $actor->id;
    }
}
