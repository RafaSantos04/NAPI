<?php

namespace App\Http\Controllers\Web\Admin;

use App\Domain\IAM\Actions\ActivateUser;
use App\Domain\IAM\Actions\DeactivateUser;
use App\Domain\IAM\AuditContext;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Same Policy abilities and Actions as the API's UserStatusController. The
 * last-administrator conflict comes back as a flash error, rendered by the
 * domain exception itself.
 */
class UserStatusController extends Controller
{
    public function deactivate(Request $request, User $user): RedirectResponse
    {
        $this->authorize('deactivate', $user);

        DeactivateUser::execute($user, AuditContext::fromRequest($request));

        return redirect()->route('admin.users.show', $user)->with('status', 'Usuário desativado.');
    }

    public function activate(Request $request, User $user): RedirectResponse
    {
        $this->authorize('activate', $user);

        ActivateUser::execute($user, AuditContext::fromRequest($request));

        return redirect()->route('admin.users.show', $user)->with('status', 'Usuário ativado.');
    }
}
