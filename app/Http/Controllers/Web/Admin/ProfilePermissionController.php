<?php

namespace App\Http\Controllers\Web\Admin;

use App\Domain\IAM\Actions\SyncMenuPermissions;
use App\Domain\IAM\AuditContext;
use App\DTOs\SyncMenuPermissionsDto;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Admin\Profile\SyncPermissionsRequest;
use App\Models\Profile;
use Illuminate\Http\RedirectResponse;

/**
 * Saves a profile's permission matrix with the same request rules, Policy
 * (ProfilePolicy::syncMenus, anti escalation included) and Action as
 * PUT /api/v1/profiles/{profile}/menus.
 */
class ProfilePermissionController extends Controller
{
    public function update(SyncPermissionsRequest $request, Profile $profile): RedirectResponse
    {
        SyncMenuPermissions::execute($profile, SyncMenuPermissionsDto::from($request), AuditContext::fromRequest($request));

        return redirect()->route('admin.profiles.show', $profile)->with('status', 'Permissões atualizadas.');
    }
}
