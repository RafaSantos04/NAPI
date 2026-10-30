<?php

namespace App\Policies;

use App\Http\Admin\AdminNavigation;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Where a token may be issued (ADR-0013, items 10 to 12). A privileged
 * account, one that enters the admin area, gets its tokens only there,
 * after confirming its password. The API login and POST /tokens refuse it,
 * or a password or token would bypass that confirmation.
 *
 * "Privileged" is AdminNavigation::allows(), the rule that already opens the
 * admin area: no flag or list of its own (ADR-0008).
 */
class PersonalAccessTokenPolicy
{
    public function issueViaApi(User $user): Response
    {
        return AdminNavigation::allows($user)
            ? Response::deny('Privileged accounts get their tokens in the admin area.')
            : Response::allow();
    }
}
