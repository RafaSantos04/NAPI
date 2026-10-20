<?php

namespace App\Http\Controllers\Web\Admin;

use App\Domain\IAM\Actions\SyncMenuProfiles;
use App\Domain\IAM\AuditContext;
use App\DTOs\SyncMenuProfilesDto;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Admin\Menu\SyncProfilesRequest;
use App\Models\Menu;
use Illuminate\Http\RedirectResponse;

/**
 * Saves which profiles hold which actions on one menu: the permission
 * matrix from the menu's side, with the same Policy and Action underneath
 * as the profile page (ProfilePolicy::syncMenus, SyncMenuPermissions).
 */
class MenuPermissionController extends Controller
{
    public function update(SyncProfilesRequest $request, Menu $menu): RedirectResponse
    {
        SyncMenuProfiles::execute($menu, SyncMenuProfilesDto::from($request), AuditContext::fromRequest($request));

        return redirect()->route('admin.menus.edit', $menu)->with('status', 'Perfis do menu atualizados.');
    }
}
