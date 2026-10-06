<?php

namespace App\Http\Controllers\Web\Admin;

use App\Domain\IAM\Actions\AssignProfilesToUser;
use App\Domain\IAM\AuditContext;
use App\DTOs\AssignProfilesToUserDto;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Admin\User\AssignProfilesRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

/**
 * Anti-escalation rules come from UserPolicy::assignProfiles (via the
 * request) and the last-administrator invariant from the Action, exactly as
 * in the API.
 */
class UserProfileController extends Controller
{
    public function update(AssignProfilesRequest $request, User $user): RedirectResponse
    {
        AssignProfilesToUser::execute($user, AssignProfilesToUserDto::from($request), AuditContext::fromRequest($request));

        return redirect()->route('admin.users.show', $user)->with('status', 'Perfis atualizados.');
    }
}
